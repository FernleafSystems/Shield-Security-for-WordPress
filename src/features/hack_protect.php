<?php

if ( class_exists( 'ICWP_WPSF_FeatureHandler_HackProtect', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_wpsf.php' );

class ICWP_WPSF_FeatureHandler_HackProtect extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	protected function doPostConstruction() {
		$this->setCustomCronSchedules();
	}

	/**
	 * @param array $aAjaxResponse
	 * @return array
	 */
	public function handleAuthAjax( $aAjaxResponse ) {

		if ( empty( $aAjaxResponse ) ) {
			switch ( $this->loadRequest()->request( 'exec' ) ) {

				case 'plugin_reinstall':
					$aAjaxResponse = $this->ajaxExec_PluginReinstall();
					break;

				default:
					break;
			}
		}
		return parent::handleAuthAjax( $aAjaxResponse );
	}

	/**
	 */
	protected function doExtraSubmitProcessing() {

		$this->clearIcSnapshots();
		$this->clearCrons();
		$this->cleanFileExclusions();
		$this->cleanPtgFileExtensions();

		$oOpts = $this->getOptionsVo();
		if ( !$this->isPtgEnabled() || $oOpts->isOptChanged( 'ptg_depth' ) || $oOpts->isOptChanged( 'ptg_extensions' ) ) {
			/** @var ICWP_WPSF_Processor_HackProtect $oP */
			$oP = $this->getProcessor();
			$oP->getSubProcessorGuard()
			   ->deleteStores();
			$this->setPtgLastBuildAt( 0 );
		}

		$this->setOpt( 'ptg_candiskwrite_at', 0 );
	}

	protected function clearCrons() {
		$aCrons = array(
			$this->getIcCronName(),
			$this->getUfcCronName(),
			$this->getWcfCronName(),
			$this->getPtgCronName()
		);
		$oCron = $this->loadWpCronProcessor();
		foreach ( $aCrons as $sCron ) {
			$oCron->deleteCronJob( $sCron );
		}
	}

	/**
	 * @param string $sScan ptg, wcf, ufc, wpv
	 * @return int
	 */
	public function getLastScanAt( $sScan ) {
		return (int)$this->getOpt( sprintf( 'insights_last_scan_%s_at', $sScan ), 0 );
	}

	/**
	 * @param string $sScan ptg, wcf, ufc, wpv
	 * @return int
	 */
	public function getLastScanProblemAt( $sScan ) {
		return (int)$this->getOpt( sprintf( 'last_scan_problem_%s_at', $sScan ), 0 );
	}

	/**
	 * @param string $sScan ptg, wcf, ufc, wpv
	 * @return bool
	 */
	public function getScanHasProblem( $sScan ) {
		$nLastProb = $this->getLastScanProblemAt( $sScan );
		return ( $nLastProb > 0 ) && ( $nLastProb - $this->getLastScanAt( $sScan ) >= 0 );
	}

	/**
	 * @return bool
	 */
	public function isIncludeFileLists() {
		return $this->isPremium() && $this->isOpt( 'email_files_list', 'Y' );
	}

	/**
	 * @param string $sScan ptg, wcf, ufc, wpv
	 * @return $this
	 */
	public function setLastScanAt( $sScan ) {
		return $this->setOptInsightsAt( sprintf( 'last_scan_%s_at', $sScan ) );
	}

	/**
	 * @param string $sScan ptg, wcf, ufc, wpv
	 * @return $this
	 */
	public function setLastScanProblemAt( $sScan ) {
		return $this->setOptAt( sprintf( 'last_scan_problem_%s_at', $sScan ) );
	}

	/**
	 * @param string $sScan ptg, wcf, ufc, wpv
	 * @return $this
	 */
	public function clearLastScanProblemAt( $sScan ) {
		return $this->setOptAt( sprintf( 'last_scan_problem_%s_at', $sScan ), 0 );
	}

	/**
	 * @return int
	 */
	public function getScanFrequency() {
		return (int)$this->getOpt( 'scan_frequency', 1 );
	}

	/**
	 * @return $this
	 */
	protected function setCustomCronSchedules() {
		$nFreq = $this->getScanFrequency();
		$this->loadWpCronProcessor()
			 ->addNewSchedule(
				 $this->prefix( sprintf( 'per-day-%s', $nFreq ) ),
				 array(
					 'interval' => DAY_IN_SECONDS/$nFreq,
					 'display'  => sprintf( _wpsf__( '%s per day' ), $nFreq )
				 )
			 );
		return $this;
	}

	/**
	 * @return $this
	 */
	protected function clearIcSnapshots() {
		return $this->setIcSnapshotUsers( array() );
	}

	/**
	 * @return bool
	 */
	public function isIcEnabled() {
		return $this->isOpt( 'ic_enabled', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isIcUsersEnabled() {
		return $this->isOpt( 'ic_users', 'Y' );
	}

	/**
	 * @return string
	 */
	public function getIcCronName() {
		return $this->prefix( $this->getDef( 'cron_name_integrity_check' ) );
	}

	/**
	 * @param array[] $aUsers
	 * @return $this
	 */
	public function setIcSnapshotUsers( $aUsers ) {
		return $this->setOpt( 'snapshot_users', $aUsers );
	}

	/**
	 * @return string
	 */
	public function getUnrecognisedFileScannerOption() {
		return $this->getOpt( 'enable_unrecognised_file_cleaner_scan', 'disabled' );
	}

	/**
	 * @return string
	 */
	public function getUfcCronName() {
		return $this->prefixOptionKey( $this->getDef( 'unrecognisedscan_cron_name' ) );
	}

	/**
	 * @return array
	 */
	public function getUfcFileExclusions() {
		$aExclusions = $this->getOpt( 'ufc_exclusions', array() );
		if ( empty( $aExclusions ) || !is_array( $aExclusions ) ) {
			$aExclusions = array();
		}
		return $aExclusions;
	}

	/**
	 * @param string $sOption
	 * @return $this
	 */
	public function setUfcOption( $sOption ) {
		return $this->setOpt( 'enable_unrecognised_file_cleaner_scan', $sOption );
	}

	/**
	 * @param array $aExclusions
	 * @return $this
	 */
	public function setUfcFileExclusions( $aExclusions ) {
		if ( !is_array( $aExclusions ) ) {
			$aExclusions = array();
		}
		return $this->setOpt( 'ufc_exclusions', array_filter( array_map( 'trim', $aExclusions ) ) );
	}

	/**
	 * @return $this
	 */
	protected function cleanFileExclusions() {
		$aExclusions = array();

		$oFS = $this->loadFS();
		foreach ( $this->getUfcFileExclusions() as $nKey => $sExclusion ) {
			$sExclusion = $oFS->normalizeFilePathDS( trim( $sExclusion ) );

			if ( preg_match( '/^#(.+)#$/', $sExclusion, $aMatches ) ) { // it's regex
				// ignore it
			}
			else if ( strpos( $sExclusion, '/' ) === false ) { // filename only
				$sExclusion = trim( preg_replace( '#[^\.0-9a-z_-]#i', '', $sExclusion ) );
			}

			if ( !empty( $sExclusion ) ) {
				$aExclusions[] = $sExclusion;
			}
		}

		return $this->setOpt( 'ufc_exclusions', array_unique( $aExclusions ) );
	}

	/**
	 * @return string
	 */
	public function isUfcDeleteFiles() {
		return in_array( $this->getUnrecognisedFileScannerOption(), array(
			'enabled_delete_only',
			'enabled_delete_report'
		) );
	}

	/**
	 * @return bool
	 */
	public function isUfcEnabled() {
		return ( $this->getUnrecognisedFileScannerOption() != 'disabled' );
	}

	/**
	 * @return bool
	 */
	public function isUfsScanUploads() {
		return $this->isOpt( 'ufc_scan_uploads', 'Y' );
	}

	/**
	 * @return string
	 */
	public function isUfsSendReport() {
		return in_array( $this->getUnrecognisedFileScannerOption(), array(
			'enabled_report_only',
			'enabled_delete_report'
		) );
	}

	/**
	 * @return string
	 */
	public function getWcfCronName() {
		return $this->prefixOptionKey( $this->getDef( 'corechecksum_cron_name' ) );
	}

	/**
	 * @return bool
	 */
	public function isWcfScanAutoRepair() {
		return $this->isOpt( 'attempt_auto_file_repair', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isWcfScanEnabled() {
		return $this->isOpt( 'enable_core_file_integrity_scan', 'Y' );
	}

	/**
	 * @param bool $bEnabled
	 * @return $this
	 */
	public function setWcfScanEnabled( $bEnabled ) {
		return $this->setOpt( 'enable_core_file_integrity_scan', $bEnabled ? 'Y' : 'N' );
	}

	/**
	 * @param bool $bEnabled
	 * @return $this
	 */
	public function setWcfScanAutoRepair( $bEnabled ) {
		return $this->setOpt( 'attempt_auto_file_repair', $bEnabled ? 'Y' : 'N' );
	}

	/**
	 * @param int $nId
	 * @return $this
	 */
	public function addWpvulnNotifiedId( $nId ) {
		if ( !$this->isWpvulnIdAlreadyNotified( $nId ) ) {
			$aIds = $this->getWpvulnNotifiedIds();
			$aIds[] = (int)$nId;
			$this->setOpt( 'wpvuln_notified_ids', $aIds );
		}
		return $this;
	}

	/**
	 * @return bool
	 */
	public function isWpvulnEnabled() {
		return $this->isPremium() && !$this->isOpt( 'enable_wpvuln_scan', 'disabled' );
	}

	/**
	 * @return array
	 */
	public function getWpvulnNotifiedIds() {
		$a = $this->getOpt( 'wpvuln_notified_ids', array() );
		return is_array( $a ) ? $a : array();
	}

	/**
	 * @param int $nId
	 * @return bool
	 */
	public function isWpvulnIdAlreadyNotified( $nId ) {
		return in_array( $nId, $this->getWpvulnNotifiedIds() );
	}

	/**
	 * @return bool
	 */
	public function isWpvulnSendEmail() {
		return $this->isWpvulnEnabled() && $this->isOpt( 'enable_wpvuln_scan', 'enabled_email' );
	}

	/**
	 * @return bool
	 */
	public function isWpvulnAutoupdatesEnabled() {
		return $this->isWpvulnEnabled() && $this->isOpt( 'wpvuln_scan_autoupdate', 'Y' );
	}

	/**
	 * @return mixed
	 */
	public function getWpvulnPluginsHighlightOption() {
		return $this->isWpvulnEnabled() ? $this->getOpt( 'wpvuln_scan_display' ) : 'disabled';
	}

	/**
	 * @return bool
	 */
	public function isWpvulnPluginsHighlightEnabled() {
		$sOpt = $this->getWpvulnPluginsHighlightOption();
		return ( $sOpt != 'disabled' ) && $this->loadWpUsers()->isUserAdmin()
			   && ( ( $sOpt != 'enabled_securityadmin' ) || $this->getConn()->getHasPermissionToManage() );
	}

	/**
	 * @return bool
	 */
	public function canPtgWriteToDisk() {
		$bCan = (bool)$this->getOpt( 'ptg_candiskwrite' );
		$nNow = $this->loadRequest()->ts();

		$bLastCheckExpired = ( $nNow - $this->getOpt( 'ptg_candiskwrite_at', 0 ) ) > DAY_IN_SECONDS;
		if ( !$bCan && $bLastCheckExpired ) {
			$oFS = $this->loadFS();
			$sDir = $this->getPtgSnapsBaseDir();

			if ( $oFS->mkdir( $sDir ) ) {
				$sTestFile = path_join( $sDir, 'test.txt' );
				$oFS->putFileContent( $sTestFile, 'test-'.$nNow );
				$sContents = $oFS->exists( $sTestFile ) ? $oFS->getFileContent( $sTestFile ) : '';
				if ( $sContents === 'test-'.$nNow ) {
					$oFS->deleteFile( $sTestFile );
					$this->setOpt( 'ptg_candiskwrite', !$oFS->exists( $sTestFile ) );
				}
			}
			$this->setOpt( 'ptg_candiskwrite_at', $nNow );
		}

		return $bCan;
	}

	/**
	 * @return $this
	 */
	protected function cleanPtgFileExtensions() {
		return $this->setOpt(
			'ptg_extensions',
			$this->cleanStringArray( $this->getPtgFileExtensions(), '#[^a-z0-9_-]#i' )
		);
	}

	/**
	 * @return bool
	 */
	public function getPtgCronName() {
		return $this->prefixOptionKey( $this->getDef( 'ptg_cronname' ) );
	}

	/**
	 * @return string[]
	 */
	public function getPtgFileExtensions() {
		return $this->getOpt( 'ptg_extensions' );
	}

	/**
	 * @return bool
	 */
	public function getPtgDepth() {
		return $this->getOpt( 'ptg_depth' );
	}

	/**
	 * @return array
	 */
	public function getPtgEmailTrackData() {
		$aData = $this->getOpt( 'ptg_email_track' );
		return is_array( $aData ) ? $aData : array();
	}

	/**
	 * @return string
	 */
	public function getPtgEnabledOption() {
		return $this->getOpt( 'ptg_enable' );
	}

	/**
	 * @return int
	 */
	public function getPtgLastBuildAt() {
		return $this->getOpt( 'ptg_last_build_at' );
	}

	/**
	 * @return string
	 */
	public function getPtgSnapsBaseDir() {
		return path_join( WP_CONTENT_DIR, 'shield/ptguard' );
	}

	/**
	 * @return bool
	 */
	public function isPtgBuildRequired() {
		return $this->isPtgEnabled() && ( $this->getPtgLastBuildAt() == 0 );
	}

	/**
	 * @return bool
	 */
	public function isPtgEnabled() {
		return $this->isPremium() && $this->isOpt( 'ptg_enable', 'enabled' )
			   && $this->getOptionsVo()->isOptReqsMet( 'ptg_enable' )
			   && $this->canPtgWriteToDisk();
	}

	/**
	 * @return bool
	 */
	public function isPtgReadyToScan() {
		return $this->isPtgEnabled() && !$this->isPtgBuildRequired();
	}

	/**
	 * @return bool
	 */
	public function isPtgReinstallLinks() {
		return $this->isPtgEnabled() && $this->isOpt( 'ptg_reinstall_links', 'Y' );
	}

	/**
	 * @param array $aData
	 * @return $this
	 */
	public function setPtgEmailTrackData( $aData ) {
		return $this->setOpt( 'ptg_email_track', $aData );
	}

	/**
	 * @param int $nTime
	 * @return $this
	 */
	public function setPtgLastBuildAt( $nTime = null ) {
		return $this->setOpt( 'ptg_last_build_at', is_null( $nTime ) ? $this->loadRequest()->ts() : $nTime );
	}

	/**
	 * @param string $sValue
	 * @return $this
	 */
	public function setPtgEnabledOption( $sValue ) {
		return $this->setOpt( 'ptg_enable', $sValue );
	}

	/**
	 * @return array
	 */
	public function ajaxExec_PluginReinstall() {
		$oReq = $this->loadRequest();
		$bReinstall = (bool)$oReq->post( 'reinstall' );
		$bActivate = (bool)$oReq->post( 'activate' );
		$sFile = sanitize_text_field( wp_unslash( $oReq->post( 'file' ) ) );
		$oWpP = $this->loadWpPlugins();

		if ( $bReinstall ) {
			/** @var ICWP_WPSF_Processor_HackProtect $oP */
			$oP = $this->getProcessor();
			$bActivate = $oP->getSubProcessorGuard()
							->reinstall( $sFile, ICWP_WPSF_Processor_HackProtect_PTGuard::CONTEXT_PLUGINS )
						 && $bActivate;
		}
		if ( $bActivate ) {
			$oWpP->activate( $sFile );
		}

		return array(
			'success' => true
		);
	}

	public function insertCustomJsVars_Admin() {
		parent::insertCustomJsVars_Admin();

		if ( $this->loadWp()->isCurrentPage( 'plugins.php' ) && $this->isPtgReinstallLinks() ) {
			wp_localize_script(
				$this->prefix( 'global-plugin' ),
				'icwp_wpsf_vars_hp',
				array(
					'ajax_reinstall' => $this->getAjaxActionData( 'plugin_reinstall' ),
					'reinstallable'  => $this->getReinstallablePlugins()
				)
			);
			wp_enqueue_script( 'jquery-ui-dialog' ); // jquery and jquery-ui should be dependencies, didn't check though...
			wp_enqueue_style( 'wp-jquery-ui-dialog' );
		}
	}

	/**
	 * @return string[]
	 */
	protected function getReinstallablePlugins() {
		$oWPP = $this->loadWpPlugins();
		$aP = $oWPP->getInstalledBaseFiles();
		foreach ( $aP as $nKey => $sPluginFile ) {
			if ( !$oWPP->isWpOrg( $sPluginFile ) ) {
				unset( $aP[ $nKey ] );
			}
		}
		return array_values( $aP );
	}

	/**
	 * @param string $sSectionSlug
	 * @return array
	 */
	protected function getSectionNotices( $sSectionSlug ) {
		$aNotices = array();
		switch ( $sSectionSlug ) {

			case 'section_core_file_integrity_scan':
				$nTime = $this->getLastScanAt( 'wcf' );
				break;

			case 'section_unrecognised_file_scan':
				$nTime = $this->getLastScanAt( 'ufc' );
				break;

			case 'section_pluginthemes_guard':
				$nTime = $this->getLastScanAt( 'ptg' );
				break;

			case 'section_wpvuln_scan':
				$nTime = $this->getLastScanAt( 'wpv' );
				break;

			default:
				$nTime = null;
				break;
		}

		if ( !is_null( $nTime ) ) {
			$nTime = ( $nTime > 0 ) ? $this->loadWp()->getTimeStampForDisplay( $nTime ) : _wpsf__( 'Never' );
			$aNotices[] = sprintf( _wpsf__( 'Last Scan Time: %s' ), $nTime );
		}
		return $aNotices;
	}

	/**
	 * @param string $sSection
	 * @return array
	 */
	protected function getSectionWarnings( $sSection ) {
		$aWarnings = array();

		if ( $sSection == 'section_pluginthemes_guard' ) {
			if ( !$this->canPtgWriteToDisk() ) {
				$aWarnings[] = sprintf( _wpsf__( 'Sorry, this feature is not available because we cannot write to disk at this location: "%s"' ), $this->getPtgSnapsBaseDir() );
			}
		}

		return $aWarnings;
	}

	/**
	 * @param array $aAllNotices
	 * @return array
	 */
	public function addInsightsNoticeData( $aAllNotices ) {
		$aNotices = array(
			'title'    => _wpsf__( 'Scans' ),
			'messages' => array()
		);

		{// Core files
			if ( !$this->isWcfScanEnabled() ) {
				$aNotices[ 'messages' ][ 'wcf' ] = array(
					'title'   => 'WP Core Files',
					'message' => _wpsf__( 'Core File scanner is not enabled.' ),
					'href'    => $this->getUrl_AdminPage().'#pills-section_core_file_integrity_scan',
					'action'  => sprintf( 'Go To %s', _wpsf__( 'Options' ) ),
					'rec'     => _wpsf__( 'Automatic WordPress Core File scanner should be turned-on.' )
				);
			}
			else if ( $this->getScanHasProblem( 'wcf' ) ) {
				$aNotices[ 'messages' ][ 'wcf' ] = array(
					'title'   => 'WP Core Files',
					'message' => _wpsf__( 'Modified WordPress core files found.' ),
					'href'    => $this->getUrl_Wizard( 'wcf' ),
					'action'  => _wpsf__( 'Run Scan' ),
					'rec'     => _wpsf__( 'Scan WP core files and repair any files that are flagged as modified.' )
				);
			}
		}

		{// Unrecognised
			if ( !$this->isUfcEnabled() ) {
				$aNotices[ 'messages' ][ 'ufc' ] = array(
					'title'   => 'Unrecognised Files',
					'message' => _wpsf__( 'Unrecognised File scanner is not enabled.' ),
					'href'    => $this->getUrl_AdminPage().'#pills-section_unrecognised_file_scan',
					'action'  => sprintf( 'Go To %s', _wpsf__( 'Options' ) ),
					'rec'     => _wpsf__( 'Automatic scanning for non-WordPress core files is recommended.' )
				);
			}
			else if ( $this->getScanHasProblem( 'ufc' ) ) {
				$aNotices[ 'messages' ][ 'ufc' ] = array(
					'title'   => 'Unrecognised Files',
					'message' => _wpsf__( 'Unrecognised files found in WordPress Core directory.' ),
					'href'    => $this->getUrl_Wizard( 'ufc' ),
					'action'  => _wpsf__( 'Run Scan' ),
					'rec'     => _wpsf__( 'Scan and remove any files that are not meant to be in the WP core directories.' )
				);
			}
		}

		{// Plugin/Theme Guard
			if ( !$this->isPtgEnabled() ) {
				$aNotices[ 'messages' ][ 'ptg' ] = array(
					'title'   => 'Plugin/Theme Guard',
					'message' => _wpsf__( 'Automatic Plugin/Themes Guard is not enabled.' ),
					'href'    => $this->getUrl_AdminPage().'#pills-section_pluginthemes_guard',
					'action'  => sprintf( 'Go To %s', _wpsf__( 'Options' ) ),
					'rec'     => _wpsf__( 'Automatic detection of plugin/theme modifications is recommended.' )
				);
			}
			else if ( $this->getScanHasProblem( 'ptg' ) ) {
				$aNotices[ 'messages' ][ 'ptg' ] = array(
					'title'   => 'Plugin/Theme Guard',
					'message' => _wpsf__( 'A plugin/theme was found to have been modified.' ),
					'href'    => $this->getUrl_Wizard( 'ptg' ),
					'action'  => _wpsf__( 'Run Scan' ),
					'rec'     => _wpsf__( 'Reviewing modifications to your plugins/themes is recommended.' )
				);
			}
		}

		{// Vulnerability Scanner
			if ( !$this->isWpvulnEnabled() ) {
				$aNotices[ 'messages' ][ 'wpv' ] = array(
					'title'   => 'Vulnerability Scanner',
					'message' => _wpsf__( 'Plugin Vulnerability Scanner is not enabled.' ),
					'href'    => $this->getUrl_AdminPage().'#pills-section_wpvuln_scan',
					'action'  => sprintf( 'Go To %s', _wpsf__( 'Options' ) ),
					'rec'     => _wpsf__( 'Automatic detection of plugin vulnerabilities is recommended.' )
				);
			}
			else if ( $this->getScanHasProblem( 'wpv' ) ) {
				$aNotices[ 'messages' ][ 'wpv' ] = array(
					'title'   => 'Vulnerable Plugins',
					'message' => _wpsf__( 'At least 1 plugin has known vulnerabilities.' ),
					'href'    => $this->loadWp()->getAdminUrl_Plugins( true ),
					'action'  => sprintf( 'Go To %s', _wpsf__( 'Plugins' ) ),
					'rec'     => _wpsf__( 'Plugins with known vulnerabilities should be updated, removed, or replaced.' )
				);
			}
		}

		$aNotices[ 'count' ] = count( $aNotices[ 'messages' ] );

		$aAllNotices[ 'scans' ] = $aNotices;
		return $aAllNotices;
	}

	/**
	 * @param array $aAllData
	 * @return array
	 */
	public function addInsightsConfigData( $aAllData ) {
		$aThis = array(
			'strings'  => array(
				'title' => _wpsf__( 'Hack Guard' ),
				'sub'   => _wpsf__( 'Threats/Intrusions Detection & Repair' ),
			),
			'key_opts' => array()
		);

		if ( !$this->isModOptEnabled() ) {
			$aThis[ 'key_opts' ][ 'mod' ] = $this->getModDisabledInsight();
		}
		else {
			$bCore = $this->isWcfScanEnabled();
			$aThis[ 'key_opts' ][ 'wcf' ] = array(
				'name'    => _wpsf__( 'WP Core File Scan' ),
				'enabled' => $bCore,
				'summary' => $bCore ?
					_wpsf__( 'Regularly scanning of Core files are for hacks' )
					: _wpsf__( "Core files are never scanned for hacks!" ),
				'weight'  => 2
			);
			if ( $bCore && !$this->isWcfScanAutoRepair() ) {
				$aThis[ 'key_opts' ][ 'wcf_repair' ] = array(
					'name'    => _wpsf__( 'WP Core File Repair' ),
					'enabled' => $this->isWcfScanAutoRepair(),
					'summary' => $this->isWcfScanAutoRepair() ?
						_wpsf__( 'Core files are automatically repaired' )
						: _wpsf__( "Core files aren't automatically repaired!" ),
					'weight'  => 1
				);
			}

			$bUcf = $this->isUfcEnabled();
			$aThis[ 'key_opts' ][ 'ufc' ] = array(
				'name'    => _wpsf__( 'Unrecognised Files' ),
				'enabled' => $bUcf,
				'summary' => $bUcf ?
					_wpsf__( 'Regularly scanning for unrecognised files' )
					: _wpsf__( "WP Core is never scanned for unrecognised files!" ),
				'weight'  => 2
			);
			if ( $bUcf && !$this->isUfcDeleteFiles() ) {
				$aThis[ 'key_opts' ][ 'ufc_repair' ] = array(
					'name'    => _wpsf__( 'Unrecognised Files Removal' ),
					'enabled' => $this->isUfcDeleteFiles(),
					'summary' => $this->isUfcDeleteFiles() ?
						_wpsf__( 'Unrecognised files are automatically removed' )
						: _wpsf__( "Unrecognised files aren't automatically removed!" ),
					'weight'  => 1
				);
			}

			$bWpv = $this->isWpvulnEnabled();
			$aThis[ 'key_opts' ][ 'wpv' ] = array(
				'name'    => _wpsf__( 'Vulnerability Scan' ),
				'enabled' => $bWpv,
				'summary' => $bWpv ?
					_wpsf__( 'Regularly scanning for known vulnerabilities' )
					: _wpsf__( "Plugins/Themes never scanned for vulnerabilities!" ),
				'weight'  => 2
			);
			if ( $bWpv && !$this->isWpvulnAutoupdatesEnabled() ) {
				$aThis[ 'key_opts' ][ 'wpv_repair' ] = array(
					'name'    => _wpsf__( 'Auto Update' ),
					'enabled' => $this->isWpvulnAutoupdatesEnabled(),
					'summary' => $this->isWpvulnAutoupdatesEnabled() ?
						_wpsf__( 'Vulnerable items are automatically updated' )
						: _wpsf__( "Vulnerable items aren't automatically updated!" ),
					'weight'  => 1
				);
			}

			$bPtg = $this->isPtgEnabled();
			$aThis[ 'key_opts' ][ 'ptg' ] = array(
				'name'    => _wpsf__( 'Plugin/Theme Guard' ),
				'enabled' => $bPtg,
				'summary' => $bPtg ?
					_wpsf__( 'Plugins and Themes are guarded against tampering' )
					: _wpsf__( "Plugins and Themes are never scanned for tampering!" ),
				'weight'  => 2
			);
		}

		$aAllData[ $this->getSlug() ] = $aThis;
		return $aAllData;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws Exception
	 */
	protected function loadStrings_SectionTitles( $aOptionsParams ) {

		$sSectionSlug = $aOptionsParams[ 'slug' ];
		switch ( $sSectionSlug ) {

			case 'section_scan_options' :
				$sTitle = _wpsf__( 'Scan Options' );
				$sTitleShort = _wpsf__( 'Scan Options' );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'Set how frequently the Hack Guard scans will run.' ) )
				);
				break;

			case 'section_enable_plugin_feature_hack_protection_tools' :
				$sTitle = sprintf( _wpsf__( 'Enable Module: %s' ), $this->getMainFeatureName() );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'Hack Guard is a set of tools to warn you and protect you against hacks on your site.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), sprintf( _wpsf__( 'Keep the %s feature turned on.' ), _wpsf__( 'Hack Guard' ) ) )
				);
				$sTitleShort = sprintf( _wpsf__( '%s/%s Module' ), _wpsf__( 'Enable' ), _wpsf__( 'Disable' ) );
				break;

			case 'section_wpvuln_scan' :
				$sTitle = _wpsf__( 'Vulnerabilities Scanner' );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'Regularly scan your WordPress plugins and themes for known security vulnerabilities.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), sprintf( _wpsf__( 'Keep the %s feature turned on.' ), _wpsf__( 'Plugin Vulnerabilities Scanner' ) ) ),
					_wpsf__( 'Ensure this is turned on and you will always know if any of your assets have known security vulnerabilities.' )
				);
				$sTitleShort = _wpsf__( 'Vulnerabilities Scanner' );
				break;

			case 'section_plugin_vulnerabilities_scan' :
				$sTitle = _wpsf__( 'Plugin Vulnerabilities Scanner' );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'Regularly scan your plugins against a database of known vulnerabilities.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), sprintf( _wpsf__( 'Keep the %s feature turned on.' ), _wpsf__( 'Plugin Vulnerabilities Scanner' ) ) )
				);
				$sTitleShort = _wpsf__( 'Plugin Vulnerabilities' );
				break;

			case 'section_core_file_integrity_scan' :
				$sTitle = _wpsf__( 'Core File Integrity Scanner' );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'Regularly scan your WordPress core files for changes compared to official WordPress files.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), sprintf( _wpsf__( 'Keep the %s feature turned on.' ), _wpsf__( 'Core File Integrity Scanner' ) ) )
				);
				$sTitleShort = _wpsf__( 'Core File Scanner' );
				break;

			case 'section_unrecognised_file_scan' :
				$sTitle = _wpsf__( 'Unrecognised Files Scanner' );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( "Regularly scan your WordPress core folders for files that don't belong." ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), sprintf( _wpsf__( 'Keep the %s feature turned on.' ), _wpsf__( 'Unrecognised Files Scanner' ) ) )
				);
				$sTitleShort = _wpsf__( 'Unrecognised Files Scanner' );
				break;

			case 'section_pluginthemes_guard' :
				$sTitle = _wpsf__( 'Plugins and Themes Guard' );
				$sTitleShort = _wpsf__( 'Plugins/Themes Guard' );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'Detect malicious changes to your themes and plugins.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), _wpsf__( 'Keep the Plugins/Theme Guard feature turned on.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Requirements' ), sprintf( 'PHP v%s+', '5.4.0' ) ),
				);
				break;

			case 'section_integrity_checking' :
				$sTitle = _wpsf__( 'Integrity Checks' );
				$sTitleShort = _wpsf__( 'Integrity Checks' );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'Monitor for unrecognised changes to your system.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), _wpsf__( 'Enable these to prevent unauthorized changes to your WordPress site.' ) )
				);
				break;

			default:
				throw new Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
		}
		$aOptionsParams[ 'title' ] = $sTitle;
		$aOptionsParams[ 'summary' ] = ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : array();
		$aOptionsParams[ 'title_short' ] = $sTitleShort;
		return $aOptionsParams;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws Exception
	 */
	protected function loadStrings_Options( $aOptionsParams ) {
		$sKey = $aOptionsParams[ 'key' ];
		switch ( $sKey ) {

			case 'enable_hack_protect' :
				$sName = sprintf( _wpsf__( 'Enable %s Module' ), $this->getMainFeatureName() );
				$sSummary = sprintf( _wpsf__( 'Enable (or Disable) The %s Module' ), $this->getMainFeatureName() );
				$sDescription = sprintf( _wpsf__( 'Un-Checking this option will completely disable the %s module.' ), $this->getMainFeatureName() );
				break;

			case 'scan_frequency' :
				$sName = _wpsf__( 'Daily Scan Frequency' );
				$sSummary = _wpsf__( 'Number Of Times To Automatically Run File Scan In 24hrs' );
				$sDescription = sprintf( '%s: %s', _wpsf__( 'Default' ), _wpsf__( 'Once every 24hrs.' ) )
								.'<br/>'._wpsf__( 'To improve security, increase the number of scans per day.' );
				break;

			case 'email_files_list' :
				$sName = _wpsf__( 'Email Files List' );
				$sSummary = _wpsf__( 'Scan Notification Emails Should Include Full Listing Of Files' );
				$sDescription = _wpsf__( 'Scanner notification emails will include a summary list of all affected files.' );
				break;

			case 'enable_plugin_vulnerabilities_scan' :
				$sName = _wpsf__( 'Plugin Vulnerabilities Scanner' );
				$sSummary = sprintf( _wpsf__( 'Daily Cron - %s' ), _wpsf__( 'Scans Plugins For Known Vulnerabilities' ) );
				$sDescription = _wpsf__( 'Runs a scan of all your plugins against a database of known WordPress plugin vulnerabilities.' );
				break;

			case 'enable_wpvuln_scan' :
				$sName = _wpsf__( 'Vulnerability Scanner' );
				$sSummary = _wpsf__( 'Enable The Vulnerability Scanner' );
				$sDescription = _wpsf__( 'Runs a scan of all your plugins against a database of known WordPress vulnerabilities.' );
				break;

			case 'wpvuln_scan_autoupdate' :
				$sName = _wpsf__( 'Automatic Updates' );
				$sSummary = _wpsf__( 'Apply Updates Automatically To Vulnerable Plugins' );
				$sDescription = _wpsf__( 'When an update becomes available, automatically apply updates to items with known vulnerabilities.' );
				break;

			case 'wpvuln_scan_display' :
				$sName = _wpsf__( 'Highlight Plugins' );
				$sSummary = _wpsf__( 'Highlight Vulnerable Plugins Upon Display' );
				$sDescription = _wpsf__( 'Vulnerable plugins will be highlighted on the main plugins page.' );
				break;

			case 'enable_core_file_integrity_scan' :
				$sName = _wpsf__( 'Core File Scanner' );
				$sSummary = _wpsf__( 'Scans WordPress Core Files For Alterations' );
				$sDescription = _wpsf__( 'Compares all WordPress core files on your site against the official WordPress files.' )
								.'<br />'._wpsf__( 'WordPress Core files should never be altered for any reason.' );
				break;

			case 'attempt_auto_file_repair' :
				$sName = _wpsf__( 'Auto Repair' );
				$sSummary = _wpsf__( 'Automatically Repair WordPress Core Files That Have Been Altered' );
				$sDescription = _wpsf__( 'Attempts to automatically repair WordPress Core files with the official WordPress file data, for files that have been altered or are missing.' );
				break;

			case 'enable_unrecognised_file_cleaner_scan' :
				$sName = _wpsf__( 'Unrecognised Files Scanner' );
				$sSummary = _wpsf__( 'Daily Scan For Unrecognised Files In Core Directories' );
				$sDescription = _wpsf__( 'Scans for, and automatically deletes, any files in your core WordPress folders that are not part of your WordPress installation.' );
				break;

			case 'ufc_scan_uploads' :
				$sName = _wpsf__( 'Scan Uploads' );
				$sSummary = _wpsf__( 'Scan Uploads Folder For PHP and Javascript' );
				$sDescription = sprintf( '%s - %s', _wpsf__( 'Warning' ), _wpsf__( 'Take care when turning on this option - if you are unsure, leave it disabled.' ) )
								.'<br />'._wpsf__( 'The Uploads folder is primarily for media, but could be used to store nefarious files.' );
				break;

			case 'ufc_exclusions' :
				$sName = _wpsf__( 'File Exclusions' );
				$sSummary = _wpsf__( 'Provide A List Of Files To Be Excluded From The Scan' );
				$sDefaults = implode( ', ', $this->getOptionsVo()->getOptDefault( 'ufc_exclusions' ) );
				$sDescription = _wpsf__( 'Take a new line for each file you wish to exclude from the scan.' )
								.'<br/><strong>'._wpsf__( 'No commas are necessary.' ).'</strong>'
								.'<br/>'.sprintf( '%s: %s', _wpsf__( 'Default' ), $sDefaults );
				break;

			case 'ic_enabled' :
				$sName = _wpsf__( 'Enable Integrity Scan' );
				$sSummary = _wpsf__( 'Scans For Critical Changes Made To Your WordPress Site' );
				$sDescription = _wpsf__( 'Detects changes made to your WordPress site outside of WordPress.' );
				break;

			case 'ic_users' :
				$sName = _wpsf__( 'Monitor User Accounts' );
				$sSummary = _wpsf__( 'Scans For Critical Changes Made To User Accounts' );
				$sDescription = sprintf( _wpsf__( 'Detects changes made to critical user account information that were made directly on the database and outside of the WordPress system.' ), 'author=' )
								.'<br />'._wpsf__( 'An example of this might be some form of SQL Injection attack.' )
								.'<br />'.sprintf( '%s: %s', _wpsf__( 'Warning' ), _wpsf__( 'Enabling this option for every page low may slow down your site with large numbers of users.' ) )
								.'<br />'.sprintf( '%s: %s', _wpsf__( 'Warning' ), _wpsf__( 'This option may cause critical problem with 3rd party plugins that manage user accounts.' ) );
				break;

			case 'ptg_enable' :
				$sName = sprintf( _wpsf__( 'Enable %s' ), _wpsf__( 'Guard' ) );
				$sSummary = _wpsf__( 'Enable The Guard For Plugin And Theme Files' );
				$sDescription = _wpsf__( 'When enabled the Guard will automatically scan for changes to your Plugin and Theme files.' );
				break;

			case 'ptg_depth' :
				$sName = _wpsf__( 'Guard/Scan Depth' );
				$sSummary = _wpsf__( 'How Deep Into The Plugin Directories To Scan And Guard' );
				$sDescription = _wpsf__( 'The Guard normally scans only the top level of a folder. Increasing depth will increase scan times.' )
								.'<br/>'.sprintf( _wpsf__( 'Setting it to %s will remove this limit and all sub-folders will be scanned - not recommended' ), 0 );
				break;

			case 'ptg_extensions' :
				$sName = _wpsf__( 'Included File Types' );
				$sSummary = _wpsf__( 'The File Types (by File Extension) Included In The Scan' );
				$sDescription = _wpsf__( 'Take a new line for each file extension.' )
								.'<br/>'._wpsf__( 'No commas(,) or periods(.) necessary.' )
								.'<br/>'._wpsf__( 'Remove all extensions to scan all file type (not recommended).' );
				break;

			case 'ptg_reinstall_links' :
				$sName = _wpsf__( 'Show Re-Install Links' );
				$sSummary = _wpsf__( 'Show Re-Install Links For Plugins' );
				$sDescription = _wpsf__( "Show links to re-install plugins and offer re-install when activating plugins." );
				break;

			default:
				throw new Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
		}

		$aOptionsParams[ 'name' ] = $sName;
		$aOptionsParams[ 'summary' ] = $sSummary;
		$aOptionsParams[ 'description' ] = $sDescription;
		return $aOptionsParams;
	}
}