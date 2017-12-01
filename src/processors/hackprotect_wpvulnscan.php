<?php

if ( class_exists( 'ICWP_WPSF_Processor_HackProtect_WpVulnScan', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).ICWP_DS.'base_wpsf.php' );

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
	 * @var int
	 */
	protected $nColumnsCount;

	/**
	 */
	public function run() {
//		$this->setupVulnScanCron();
		if ( $this->loadDataProcessor()->FetchGet( 'force_wpvulnscan' ) == 1 ) {
			$this->scanPlugins();
			die();
		}

		// For display on the Plugins page
		add_action( 'admin_init', array( $this, 'addPluginVulnerabilityRows' ), 10, 2 );

		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getFeature();
		if ( $oFO->isWpvulnAutoupdatesEnabled() ) {
			add_filter( 'auto_update_plugin', array( $this, 'autoupdateVulnerablePlugins' ), 100, 2 );
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

		if ( $oFO->isWpvulnPluginsHighlightEnabled() ) {
			add_filter( 'manage_plugins_columns', array( $this, 'fCountColumns' ), 1000 );
			foreach ( array_keys( $this->loadWpPlugins()->getPlugins() ) as $sPluginFile ) {
				add_action( "after_plugin_row_$sPluginFile", array( $this, 'attachVulnerabilityWarning' ), 100, 2 );
			}
		}
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

		$aPlugin = $this->loadWpPlugins()
						->getPlugin( $sFile );

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
		$sRecipient = $this->getPluginDefaultRecipientAddress();
		$bSendSuccess = $this->getEmailProcessor()->sendEmailTo( $sRecipient, $sSubject, $this->aNotifEmail );

		if ( $bSendSuccess ) {
			$this->addToAuditEntry( sprintf( _wpsf__( 'Successfully sent Plugin Vulnerability Notification email alert to: %s' ), $sRecipient ) );
		}
		else {
			$this->addToAuditEntry( sprintf( _wpsf__( 'Failed to send Plugin Vulnerability Notification email alert to: %s' ), $sRecipient ) );
		}
		return $bSendSuccess;
	}

	public function cron_dailyWpVulnScan() {
		$this->scanPlugins();
		$this->scanThemes();
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
		$aApplicable = array();

		foreach ( $this->loadWpPlugins()->getInstalledPluginFiles() as $sFile ) {

			$aThisVulns = $this->getPluginVulnerabilities( $sFile );
			if ( !empty( $aThisVulns ) ) {
				$aApplicable[ $sFile ] = $aThisVulns;
			}
		}

		return $aApplicable;
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
		if ( empty( $sFullContent ) ) {
			$sUrl = $this->getApiRootUrl().'plugins/'.$sSlug;
			$sFullContent = $this->loadFS()->getUrlContent( $sUrl );
		}

		$oWp->setTransient( $sTransientKey, $sFullContent, DAY_IN_SECONDS );

		$aVulns = array();
		if ( !empty( $sFullContent ) ) {
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