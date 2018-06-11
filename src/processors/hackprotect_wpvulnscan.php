<?php

if ( class_exists( 'ICWP_WPSF_Processor_HackProtect_WpVulnScan', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_wpsf.php' );

class ICWP_WPSF_Processor_HackProtect_WpVulnScan extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 * @var string
	 */
	protected $sApiRootUrl;

	/**
	 * @var
	 */
	protected $aNotifEmail;

	/**
	 * @var ICWP_WPSF_WpVulnVO[][]
	 */
	protected $aPluginVulnerabilities;

	/**
	 * @var int
	 */
	protected $nColumnsCount;

	/**
	 */
	public function run() {

		// For display on the Plugins page
		add_action( 'load-plugins.php', array( $this, 'addPluginVulnerabilityRows' ), 10, 2 );

		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getFeature();
		if ( $oFO->isWpvulnAutoupdatesEnabled() ) {
			add_filter( 'auto_update_plugin', array( $this, 'autoupdateVulnerablePlugins' ), PHP_INT_MAX, 2 );
		}

		try {
			$this->setupVulnScanCron();
		}
		catch ( Exception $oE ) {
			error_log( $oE->getMessage() );
		}
	}

	/**
	 * @param bool            $bDoAutoUpdate
	 * @param StdClass|string $mItem
	 * @return boolean
	 */
	public function autoupdateVulnerablePlugins( $bDoAutoUpdate, $mItem ) {
		$sItemFile = $this->loadWp()->getFileFromAutomaticUpdateItem( $mItem );
		// TODO Audit.
		return $bDoAutoUpdate || $this->getPluginHasVulnerabilities( $sItemFile );
	}

	/**
	 * @param array $aColumns
	 * @return array
	 */
	public function fCountColumns( $aColumns ) {
		if ( !isset( $this->nColumnsCount ) ) {
			$this->nColumnsCount = count( $aColumns );
		}
		return $aColumns;
	}

	public function addPluginVulnerabilityRows() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getFeature();

		if ( $oFO->isWpvulnPluginsHighlightEnabled() && $this->getHasVulnerablePlugins() ) {
			// These 3 add the 'Vulnerable' plugin status view.
			// BUG: when vulnerable is active, only 1 plugin is available to "All" status. don't know fix.
			add_action( 'pre_current_active_plugins', array( $this, 'addVulnerablePluginStatusView' ), 1000 );
			add_filter( 'all_plugins', array( $this, 'filterPluginsToView' ), 1000 );
			add_filter( 'views_plugins', array( $this, 'addPluginsStatusViewLink' ), 1000 );

			add_filter( 'manage_plugins_columns', array( $this, 'fCountColumns' ), 1000 );
			foreach ( array_keys( $this->loadWpPlugins()->getPlugins() ) as $sPluginFile ) {
				add_action( "after_plugin_row_$sPluginFile", array( $this, 'attachVulnerabilityWarning' ), 100, 2 );
			}
		}
	}

	public function addVulnerablePluginStatusView() {
		if ( $this->loadDP()->query( 'plugin_status' ) == 'vulnerable' ) {
			global $status;
			$status = 'vulnerable';
		}
		add_filter( 'views_plugins', array( $this, 'addPluginsStatusViewLink' ), 1000 );
	}

	/**
	 * FILTER
	 * @param array $aViews
	 * @return array
	 */
	public function addPluginsStatusViewLink( $aViews ) {
		global $status;

		$nTotalVulnerable = number_format_i18n( count( $this->getVulnerablePlugins() ) );
		$aViews[ 'vulnerable' ] = sprintf( "<a href='%s' %s>%s</a>",
			add_query_arg( 'plugin_status', 'vulnerable', 'plugins.php' ),
			( 'vulnerable' === $status ) ? ' class="current"' : '',
			sprintf( '%s <span class="count">(%s)</span>', _wpsf__( 'Vulnerable' ), $nTotalVulnerable )
		);
		return $aViews;
	}

	/**
	 * FILTER
	 * @param array $aPlugins
	 * @return array
	 */
	public function filterPluginsToView( $aPlugins ) {
		if ( $this->loadDP()->query( 'plugin_status' ) == 'vulnerable' ) {
			global $status;
			$status = 'vulnerable';
			$aPlugins = array_intersect_key( $aPlugins, $this->getVulnerablePlugins() );
		}
		return $aPlugins;
	}

	/**
	 * @param string $sPluginFile
	 * @param array  $aPluginData
	 */
	public function attachVulnerabilityWarning( $sPluginFile, $aPluginData ) {

		$aVuln = $this->getPluginVulnerabilities( $sPluginFile );
		if ( count( $aVuln ) ) {
			$sOurName = $this->getController()->getHumanName();
			$aRenderData = array(
				'strings'  => array(
					'known_vuln'     => sprintf( _wpsf__( '%s has discovered that the currently installed version of the %s plugin has known security vulnerabilities.' ),
						$sOurName, '<strong>'.$aPluginData[ 'Name' ].'</strong>' ),
					'name'           => _wpsf__( 'Vulnerability Name' ),
					'type'           => _wpsf__( 'Vulnerability Type' ),
					'fixed_versions' => _wpsf__( 'Fixed Versions' ),
					'more_info'      => _wpsf__( 'More Info' ),
				),
				'vulns'    => $aVuln,
				'nColspan' => $this->nColumnsCount
			);
			echo $this->getFeature()
					  ->renderTemplate( 'snippets/plugin-vulnerability.php', $aRenderData );
		}
	}

	/**
	 * @param string             $sFile
	 * @param ICWP_WPSF_WpVulnVO $oVuln
	 * @return $this
	 */
	protected function addVulnToEmail( $sFile, $oVuln ) {
		if ( !isset( $this->aNotifEmail ) ) {
			$this->aNotifEmail = array();
		}

		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getFeature();
		if ( !$oFO->isWpvulnIdAlreadyNotified( $oVuln->getId() ) ) {

			$oFO->addWpvulnNotifiedId( $oVuln->getId() );

			$aPlugin = $this->loadWpPlugins()->getPlugin( $sFile );
			$this->aNotifEmail = array_merge(
				$this->aNotifEmail,
				array(
					'- '.sprintf( _wpsf__( 'Plugin Name: %s' ), $aPlugin[ 'Name' ] ),
					'- '.sprintf( _wpsf__( 'Vulnerability Title: %s' ), $oVuln->getTitle() ),
					'- '.sprintf( _wpsf__( 'Vulnerability Type: %s' ), $oVuln->getType() ),
					'- '.sprintf( _wpsf__( 'Fixed Version: %s' ), $oVuln->getVersionFixedIn() ),
					'- '.sprintf( _wpsf__( 'Further Information: %s' ), $oVuln->getUrl() ),
					'',
				)
			);
		}

		return $this;
	}

	/**
	 * @param stdClass $oVuln
	 * @return string
	 */
	protected function getVulnUrl( $oVuln ) {
		return sprintf( 'https://wpvulndb.com/vulnerabilities/%s', $oVuln->id );
	}

	/**
	 * @return bool
	 */
	protected function sendVulnerabilityNotification() {
		if ( empty( $this->aNotifEmail ) ) {
			return true;
		}
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getFeature();
		$oWp = $this->loadWp();
		$oConn = $this->getController();

		$aPreamble = array(
			sprintf( _wpsf__( '%s has detected plugins with known security vulnerabilities.' ), $oConn->getHumanName() ),
			_wpsf__( 'Details for the plugin(s) are below:' ),
			'',
		);
		$this->aNotifEmail = array_merge( $aPreamble, $this->aNotifEmail );

		$this->aNotifEmail[] = _wpsf__( 'You should update or remove these plugins at your earliest convenience.' );
		$this->aNotifEmail[] = sprintf( _wpsf__( 'Go To Your Plugins: %s' ), $oWp->getAdminUrl_Plugins( $oConn->getIsWpmsNetworkAdminOnly() ) );

		$sSubject = sprintf( _wpsf__( 'Warning - %s' ), _wpsf__( 'Plugin(s) Discovered With Known Security Vulnerabilities.' ) );
		$sTo = $oFO->getPluginDefaultRecipientAddress();
		$bSendSuccess = $this->getEmailProcessor()
							 ->sendEmailWithWrap( $sTo, $sSubject, $this->aNotifEmail );

		if ( $bSendSuccess ) {
			$this->addToAuditEntry( sprintf( _wpsf__( 'Successfully sent Plugin Vulnerability Notification email alert to: %s' ), $sTo ) );
		}
		else {
			$this->addToAuditEntry( sprintf( _wpsf__( 'Failed to send Plugin Vulnerability Notification email alert to: %s' ), $sTo ) );
		}
		return $bSendSuccess;
	}

	public function cron_dailyWpVulnScan() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getFeature();

		$this->scanPlugins();
		$this->scanThemes();

		$this->getHasVulnerablePlugins() ? $oFO->setLastScanProblemAt( 'wpv' ) : $oFO->clearLastScanProblemAt( 'wpv' );
		$oFO->setLastScanAt( 'wpv' );
	}

	protected function scanPlugins() {

		foreach ( $this->getVulnerablePlugins() as $sFile => $aVulnerabilities ) {
			foreach ( $aVulnerabilities as $oVuln ) {
				$this->addVulnToEmail( $sFile, $oVuln );
			}
		}

		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getFeature();
		if ( $oFO->isWpvulnSendEmail() ) {
			$this->sendVulnerabilityNotification();
		}
	}

	/**
	 * @return ICWP_WPSF_WpVulnVO[][]
	 */
	protected function getVulnerablePlugins() {

		if ( !isset( $this->aPluginVulnerabilities ) || !is_array( $this->aPluginVulnerabilities ) ) {
			$this->aPluginVulnerabilities = array();

			foreach ( $this->loadWpPlugins()->getInstalledPluginFiles() as $sFile ) {

				$aThisVulns = $this->getPluginVulnerabilities( $sFile );
				if ( !empty( $aThisVulns ) ) {
					$this->aPluginVulnerabilities[ $sFile ] = $aThisVulns;
				}
			}
		}

		return $this->aPluginVulnerabilities;
	}

	/**
	 * @param string $sFile
	 * @return ICWP_WPSF_WpVulnVO[]
	 */
	protected function getPluginVulnerabilities( $sFile ) {
		$aThisVulns = array();
		$this->requireCommonLib( 'wpvulndb/WpVulnVO.php' );

		$aData = $this->loadWpPlugins()->getPlugin( $sFile );

		if ( !empty( $aData[ 'Version' ] ) ) {
			$sSlug = empty( $aData[ 'slug' ] ) ? substr( $sFile, 0, strpos( $sFile, '/' ) ) : $aData[ 'slug' ];

			/** @var stdClass $oVulnerabilityData */
			foreach ( $this->getVulnerabilityDataForPlugin( $sSlug ) as $oSingleVulnerabilityData ) {
				if ( $this->isVulnerable( $aData[ 'Version' ], $oSingleVulnerabilityData ) ) {
					$aThisVulns[] = new ICWP_WPSF_WpVulnVO( $oSingleVulnerabilityData );
				}
			}
		}

		return $aThisVulns;
	}

	/**
	 * @param string $sFile
	 * @return bool
	 */
	protected function getPluginHasVulnerabilities( $sFile ) {
		return count( $this->getPluginVulnerabilities( $sFile ) ) > 0;
	}

	/**
	 * @return bool
	 */
	protected function getHasVulnerablePlugins() {
		return count( $this->getVulnerablePlugins() ) > 0;
	}

	protected function scanThemes() {
		//TODO
	}

	/**
	 * @param $sVersion
	 * @param $oVulnerabilityData
	 * @return mixed
	 */
	protected function isVulnerable( $sVersion, $oVulnerabilityData ) {
		$sFixedVersion = empty( $oVulnerabilityData->fixed_in ) ? '0' : $oVulnerabilityData->fixed_in;
		return version_compare( $sVersion, $sFixedVersion, '<' );
	}

	/**
	 * wpvulndb_api_url_wordpress: 'https://wpvulndb.com/api/v2/wordpresses/'
	 * wpvulndb_api_url_plugins: 'https://wpvulndb.com/api/v2/plugins/'
	 * wpvulndb_api_url_themes: 'https://wpvulndb.com/api/v2/themes/'
	 * @param string $sSlug
	 * @return array
	 */
	protected function getVulnerabilityDataForPlugin( $sSlug ) {

		$oWp = $this->loadWp();
		$sTransientKey = $this->getFeature()->prefixOptionKey( 'wpvulnplugin-'.$sSlug );

		$sFullContent = $oWp->getTransient( $sTransientKey );
		if ( $sFullContent === false ) {
			$sUrl = $this->getApiRootUrl().'plugins/'.$sSlug;
			$sFullContent = $this->loadFS()->getUrlContent( $sUrl );
			if ( empty( $sFullContent ) ) {
				$sFullContent = 'not available';
			}
		}

		$oWp->setTransient( $sTransientKey, $sFullContent, DAY_IN_SECONDS );

		$aVulns = array();
		if ( !empty( $sFullContent ) && $sFullContent != 'not available' ) {
			$oData = @json_decode( $sFullContent );
			if ( isset( $oData->{$sSlug} ) && !empty( $oData->{$sSlug}->vulnerabilities ) && is_array( $oData->{$sSlug}->vulnerabilities ) ) {
				$aVulns = $oData->{$sSlug}->vulnerabilities;
			}
		}
		return $aVulns;
	}

	/**
	 * @param WP_Theme $oTheme
	 * @return array
	 */
	protected function getVulnerabilityDataForTheme( $oTheme ) {

		$sSlug = $oTheme->get_stylesheet();
		$oWp = $this->loadWp();
		$sTransientKey = $this->getFeature()->prefixOptionKey( 'wpvulntheme-'.$sSlug );

		$sFullContent = $oWp->getTransient( $sTransientKey );
		if ( empty( $sFullContent ) ) {
			$sUrl = $this->getApiRootUrl().'themes/'.$sSlug;
			$sFullContent = $this->loadFS()->getUrlContent( $sUrl );
		}

		$oWp->setTransient( $sTransientKey, $sFullContent, DAY_IN_SECONDS );

		if ( !empty( $sFullContent ) ) {
			$oData = json_decode( $sFullContent );
			if ( isset( $oData->{$sSlug} ) && !empty( $oData->{$sSlug}->vulnerabilities ) && is_array( $oData->{$sSlug}->vulnerabilities ) ) {
				return $oData->{$sSlug}->vulnerabilities;
			}
		}
		return array();
	}

	/**
	 * @throws Exception
	 */
	protected function setupVulnScanCron() {
		$this->loadWpCronProcessor()
			 ->createCronJob( $this->getCronName(), array( $this, 'cron_dailyWpVulnScan' ) );
		add_action( $this->getFeature()->prefix( 'delete_plugin' ), array( $this, 'deleteCron' ) );
	}

	/**
	 * @return string
	 */
	protected function getApiRootUrl() {
		if ( empty( $this->sApiRootUrl ) ) {
			$this->sApiRootUrl = rtrim( $this->getFeature()->getDefinition( 'wpvulndb_api_url_root' ), '/' ).'/';
		}
		return $this->sApiRootUrl;
	}

	/**
	 * @return string
	 */
	protected function getCronName() {
		$oFO = $this->getFeature();
		return $oFO->prefixOptionKey( $oFO->getDefinition( 'wpvulnscan_cron_name' ) );
	}
}