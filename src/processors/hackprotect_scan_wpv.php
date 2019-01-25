<?php

use \FernleafSystems\Wordpress\Plugin\Shield;

class ICWP_WPSF_Processor_HackProtect_Wpv extends ICWP_WPSF_Processor_HackProtect_ScanAssetsBase {

	const SCAN_SLUG = 'wpv';

	/**
	 * @var int
	 */
	private $nColumnsCount;

	/**
	 */
	public function run() {
		parent::run();

		// For display on the Plugins page
		add_action( 'load-plugins.php', array( $this, 'addPluginVulnerabilityRows' ), 10, 2 );
		add_action( 'upgrader_process_complete', array( $this, 'doScan' ), 10, 2 );
		add_action( 'deleted_plugin', array( $this, 'doScan' ), 10, 2 );

		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		if ( $oFO->isWpvulnAutoupdatesEnabled() ) {
			add_filter( 'auto_update_plugin', array( $this, 'autoupdateVulnerablePlugins' ), PHP_INT_MAX, 2 );
		}
	}

	/**
	 * @param Shield\Scans\Wpv\ResultsSet $oResults
	 * @return Shield\Databases\Scanner\EntryVO[]
	 */
	protected function convertResultsToVos( $oResults ) {
		return ( new Shield\Scans\Wpv\ConvertResultsToVos() )->convert( $oResults );
	}

	/**
	 * @param mixed|Shield\Databases\Scanner\EntryVO[] $aVos
	 * @return Shield\Scans\Wpv\ResultsSet
	 */
	protected function convertVosToResults( $aVos ) {
		return ( new Shield\Scans\Wpv\ConvertVosToResults() )->convert( $aVos );
	}

	/**
	 * @param Shield\Databases\Scanner\EntryVO $oVo
	 * @return Shield\Scans\Wpv\ResultItem
	 */
	protected function convertVoToResultItem( $oVo ) {
		return ( new Shield\Scans\Wpv\ConvertVosToResults() )->convertItem( $oVo );
	}

	/**
	 * @return Shield\Scans\Wpv\Repair
	 */
	protected function getRepairer() {
		return new Shield\Scans\Wpv\Repair();
	}

	/**
	 * @return Shield\Scans\Wpv\Scanner
	 */
	protected function getScanner() {
		return new Shield\Scans\Wpv\Scanner();
	}

	/**
	 * @param Shield\Scans\Wpv\ResultsSet $oRes
	 */
	protected function runCronAutoRepair( $oRes ) {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		if ( $oFO->isWpvulnAutoupdatesEnabled() ) {
			$this->getRepairer()->repairResultsSet( $oRes );
		}
	}

	/**
	 * @param Shield\Scans\Wpv\ResultsSet $oRes
	 * @return bool - true if user notified
	 */
	protected function runCronUserNotify( $oRes ) {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		$bSend = $oFO->isWpvulnSendEmail();
		if ( $bSend ) {
			$this->emailResults( $oRes );
		}
		return $bSend;
	}

	/**
	 * @param Shield\Scans\Wpv\ResultItem $oItem
	 * @return bool
	 * @throws \Exception
	 */
	protected function itemRepair( $oItem ) {
		return $this->getRepairer()->repairItem( $oItem );
	}

	/**
	 * @param bool            $bDoAutoUpdate
	 * @param StdClass|string $mItem
	 * @return boolean
	 */
	public function autoupdateVulnerablePlugins( $bDoAutoUpdate, $mItem ) {
		$sItemFile = $this->loadWp()->getFileFromAutomaticUpdateItem( $mItem );
		// TODO Audit.
		return $bDoAutoUpdate || ( $this->getPluginVulnerabilities( $sItemFile ) > 0 );
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
		$oFO = $this->getMod();

		if ( $oFO->isWpvulnPluginsHighlightEnabled() && $this->countVulnerablePlugins() > 0 ) {
			// These 3 add the 'Vulnerable' plugin status view.
			// BUG: when vulnerable is active, only 1 plugin is available to "All" status. don't know fix.
			add_action( 'pre_current_active_plugins', array( $this, 'addVulnerablePluginStatusView' ), 1000 );
			add_filter( 'all_plugins', array( $this, 'filterPluginsToView' ), 1000 );
			add_filter( 'views_plugins', array( $this, 'addPluginsStatusViewLink' ), 1000 );

			add_filter( 'manage_plugins_columns', array( $this, 'fCountColumns' ), 1000 );
			foreach ( $this->loadWpPlugins()->getInstalledBaseFiles() as $sPluginFile ) {
				add_action( "after_plugin_row_$sPluginFile", array( $this, 'attachVulnerabilityWarning' ), 100, 2 );
			}
		}
	}

	public function addVulnerablePluginStatusView() {
		if ( $this->loadRequest()->query( 'plugin_status' ) == 'vulnerable' ) {
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

		$aViews[ 'vulnerable' ] = sprintf( "<a href='%s' %s>%s</a>",
			add_query_arg( 'plugin_status', 'vulnerable', 'plugins.php' ),
			( 'vulnerable' === $status ) ? ' class="current"' : '',
			sprintf( '%s <span class="count">(%s)</span>',
				_wpsf__( 'Vulnerable' ),
				number_format_i18n( $this->countVulnerablePlugins() )
			)
		);
		return $aViews;
	}

	/**
	 * FILTER
	 * @param array $aPlugins
	 * @return array
	 */
	public function filterPluginsToView( $aPlugins ) {
		if ( $this->loadRequest()->query( 'plugin_status' ) == 'vulnerable' ) {
			global $status;
			$status = 'vulnerable';
			$aPlugins = array_intersect_key( $aPlugins, array_flip( $this->getVulnerablePlugins() ) );
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
			$sOurName = $this->getCon()->getHumanName();
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
			echo $this->getMod()
					  ->renderTemplate( 'snippets/plugin-vulnerability.php', $aRenderData );
		}
	}

	/**
	 * @param stdClass $oVuln
	 * @return string
	 */
	protected function getVulnUrl( $oVuln ) {
		return sprintf( 'https://wpvulndb.com/vulnerabilities/%s', $oVuln->id );
	}

	/**
	 * @param Shield\Scans\Wpv\ResultsSet $oRes
	 * @return bool
	 */
	protected function emailResults( $oRes ) {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		$oWpPlugins = $this->loadWpPlugins();
		$oWpThemes = $this->loadWpThemes();
		$oCon = $this->getCon();

		$aContent = array(
			sprintf( _wpsf__( '%s has detected items with known security vulnerabilities.' ), $oCon->getHumanName() ),
			_wpsf__( 'You should update or remove these items at your earliest convenience.' ),
			_wpsf__( 'Details for the items(s) are below:' ),
			'',
		);

		/** @var Shield\Scans\Wpv\ResultItem $oItem */
		foreach ( $oRes->getItems() as $oItem ) {

			if ( $oItem->context == 'plugins' ) {
				$aPlugin = $oWpPlugins->getPlugin( $oItem->slug );
				$sName = sprintf( '%s - %s', _wpsf__( 'Plugin' ), empty( $aPlugin ) ? 'Unknown' : $aPlugin[ 'Name' ] );
			}
			else {
				$sName = sprintf( '%s - %s', _wpsf__( 'Theme' ), $oWpThemes->getCurrentThemeName() );
			}

			$oVuln = $oItem->getWpVulnVo();
			$aContent[] = implode( "<br />", array(
				sprintf( '%s: %s', _wpsf__( 'Item' ), $sName ),
				'- '.sprintf( _wpsf__( 'Vulnerability Title: %s' ), $oVuln->title ),
				'- '.sprintf( _wpsf__( 'Vulnerability Type: %s' ), $oVuln->vuln_type ),
				'- '.sprintf( _wpsf__( 'Fixed Version: %s' ), $oVuln->fixed_in ),
				'- '.sprintf( _wpsf__( 'Further Information: %s' ), $oVuln->getUrl() ),
				'',
			) );
		}

		$aContent[] = $this->getScannerButtonForEmail();
		$aContent[] = '';

		$sSubject = sprintf( '%s - %s', _wpsf__( 'Warning' ), _wpsf__( 'Plugin(s) Discovered With Known Security Vulnerabilities.' ) );
		$sTo = $oFO->getPluginDefaultRecipientAddress();
		$bSendSuccess = $this->getEmailProcessor()
							 ->sendEmailWithWrap( $sTo, $sSubject, $aContent );

		if ( $bSendSuccess ) {
			$this->addToAuditEntry( sprintf( _wpsf__( 'Successfully sent Plugin Vulnerability Notification email alert to: %s' ), $sTo ) );
		}
		else {
			$this->addToAuditEntry( sprintf( _wpsf__( 'Failed to send Plugin Vulnerability Notification email alert to: %s' ), $sTo ) );
		}
		return $bSendSuccess;
	}

	/**
	 * @return string[]
	 */
	protected function getVulnerablePlugins() {
		return $this->getAllVulnerabilities()->getUniqueSlugs();
	}

	/**
	 * @return Shield\Scans\Wpv\ResultsSet
	 */
	protected function getAllVulnerabilities() {
		/** @var Shield\Databases\Scanner\Select $oSel */
		$oSel = $this->getScannerDb()
					 ->getDbHandler()
					 ->getQuerySelector();
		$aVos = $oSel->filterByScan( static::SCAN_SLUG )
					 ->filterByNotIgnored()
					 ->query();
		return $this->convertVosToResults( $aVos );
	}

	/**
	 * @param string $sFile
	 * @return Shield\Scans\Wpv\WpVulnDb\WpVulnVO[]
	 */
	protected function getPluginVulnerabilities( $sFile ) {
		return array_map(
			function ( $oItem ) {
				/** @var Shield\Scans\Wpv\ResultItem $oItem */
				return $oItem->getWpVulnVo();
			},
			$this->getAllVulnerabilities()->getItemsForSlug( $sFile )
		);
	}

	/**
	 * @return bool
	 */
	protected function countVulnerablePlugins() {
		return $this->getAllVulnerabilities()->countUniqueSlugsForPluginsContext();
	}

	/**
	 * @return string
	 */
	protected function getCronName() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		return $oFO->getWpvCronName();
	}
}