<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_HackProtect extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	protected function doPostConstruction() {
		parent::doPostConstruction();
		$this->setCustomCronSchedules();
	}

	/**
	 */
	protected function updateHandler() {
		$this->setPtgUpdateStoreFormat( true );
//			 ->setPtgRebuildSelfRequired( true ) // this is permanently required until a better solution is found
	}

	/**
	 */
	public function handleModRequest() {
		$oReq = Services::Request();
		switch ( $oReq->query( 'exec' ) && $this->getCon()->isPluginAdmin() ) {
			case  'scan_file_download':
				/** @var ICWP_WPSF_Processor_HackProtect $oPro */
				$oPro = $this->getProcessor();
				$oPro->getSubProScanner()->downloadItemFile( $oReq->query( 'rid' ) );
				break;
			default:
				break;
		}
	}

	/**
	 * @param array $aAjaxResponse
	 * @return array
	 */
	public function handleAuthAjax( $aAjaxResponse ) {
		$oReq = Services::Request();

		if ( empty( $aAjaxResponse ) ) {

			$sExecAction = $oReq->request( 'exec' );
			switch ( $sExecAction ) {

				case 'start_scans':
					$aAjaxResponse = $this->ajaxExec_StartScans();
					break;

				case 'bulk_action':
					$aAjaxResponse = $this->ajaxExec_ScanItemAction( $oReq->post( 'bulk_action' ) );
					break;

				case 'item_asset_accept':
				case 'item_asset_deactivate':
				case 'item_asset_reinstall':
				case 'item_delete':
				case 'item_ignore':
				case 'item_repair':
					$aAjaxResponse = $this->ajaxExec_ScanItemAction( str_replace( 'item_', '', $sExecAction ) );
					break;

				case 'render_table_scan':
					$aAjaxResponse = $this->ajaxExec_BuildTableScan();
					break;

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
	 * @param Shield\Databases\Scanner\EntryVO $oEntryVo
	 * @return string
	 */
	public function createFileDownloadLink( $oEntryVo ) {
		$aActionNonce = $this->getNonceActionData( 'scan_file_download' );
		$aActionNonce[ 'rid' ] = $oEntryVo->id;
		return add_query_arg( $aActionNonce, $this->getUrl_AdminPage() );
	}

	/**
	 * @return array
	 */
	public function ajaxExec_PluginReinstall() {
		$oReq = Services::Request();
		$bReinstall = (bool)$oReq->post( 'reinstall' );
		$bActivate = (bool)$oReq->post( 'activate' );
		$sFile = sanitize_text_field( wp_unslash( $oReq->post( 'file' ) ) );

		if ( $bReinstall ) {
			/** @var ICWP_WPSF_Processor_HackProtect $oP */
			$oP = $this->getProcessor();
			$bActivate = $oP->getSubProScanner()
							->getSubProcessorPtg()
							->reinstall( $sFile )
						 && $bActivate;
		}

		if ( $bActivate ) {
			Services::WpPlugins()->activate( $sFile );
		}

		return [ 'success' => true ];
	}

	/**
	 */
	protected function doExtraSubmitProcessing() {
		$this->clearIcSnapshots();
		$this->cleanFileExclusions();
		$this->cleanPtgFileExtensions();

		$oOpts = $this->getOptionsVo();
		if ( $oOpts->isOptChanged( 'ptg_enable' ) || $oOpts->isOptChanged( 'ptg_depth' ) || $oOpts->isOptChanged( 'ptg_extensions' ) ) {
			$this->setPtgLastBuildAt( 0 );
		}

		$this->setOpt( 'ptg_candiskwrite_at', 0 );
		$this->resetRtBackupFiles();
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
	public function getNextScanAt( $sScan ) {
		return (int)$this->getOpt( sprintf( 'next_scan_%s_at', $sScan ), 0 );
	}

	/**
	 * @param string $sScan ptg, wcf, ufc, wpv
	 * @return bool
	 */
	public function getScanHasProblem( $sScan ) {
		/** @var ICWP_WPSF_Processor_HackProtect $oPro */
		$oPro = $this->getProcessor();
		/** @var Shield\Databases\Scanner\Select $oSel */
		$oSel = $oPro->getSubProScanner()
					 ->getDbHandler()
					 ->getQuerySelector();
		return $oSel->filterByNotIgnored()
					->filterByScan( $sScan )
					->count() > 0;
	}

	/**
	 * @return int
	 */
	public function getScanNotificationInterval() {
		return DAY_IN_SECONDS*$this->getOpt( 'notification_interval' );
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
	 * @param int    $nAt
	 * @return $this
	 */
	public function setNextScanAt( $sScan, $nAt ) {
		return $this->setOptAt( sprintf( 'next_scan_%s_at', $sScan ), $nAt );
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
				 [
					 'interval' => DAY_IN_SECONDS/$nFreq,
					 'display'  => sprintf( __( '%s per day', 'wp-simple-firewall' ), $nFreq )
				 ]
			 );
		return $this;
	}

	/**
	 * @return $this
	 */
	protected function clearIcSnapshots() {
		return $this->setIcSnapshotUsers( [] );
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
	 * @return array
	 */
	public function getUfcFileExclusions() {
		$aExclusions = $this->getOpt( 'ufc_exclusions', [] );
		if ( empty( $aExclusions ) || !is_array( $aExclusions ) ) {
			$aExclusions = [];
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
			$aExclusions = [];
		}
		return $this->setOpt( 'ufc_exclusions', array_filter( array_map( 'trim', $aExclusions ) ) );
	}

	/**
	 * @return $this
	 */
	protected function cleanFileExclusions() {
		$aExclusions = [];

		foreach ( $this->getUfcFileExclusions() as $nKey => $sExclusion ) {
			$sExclusion = wp_normalize_path( trim( $sExclusion ) );

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
		return in_array( $this->getUnrecognisedFileScannerOption(), [
			'enabled_delete_only',
			'enabled_delete_report'
		] );
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
	public function isUfcSendReport() {
		return in_array( $this->getUnrecognisedFileScannerOption(), [
			'enabled_report_only',
			'enabled_delete_report'
		] );
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
	 * @return bool
	 */
	public function isWpvulnEnabled() {
		return $this->isPremium() && !$this->isOpt( 'enable_wpvuln_scan', 'disabled' );
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
		return ( $sOpt != 'disabled' ) && Services::WpUsers()->isUserAdmin()
			   && ( ( $sOpt != 'enabled_securityadmin' ) || $this->getCon()->isPluginAdmin() );
	}

	/**
	 * @return bool
	 */
	public function canPtgWriteToDisk() {
		$nNow = Services::Request()->ts();
		$bLastCheckExpired = ( $nNow - $this->getOpt( 'ptg_candiskwrite_at', 0 ) ) > DAY_IN_SECONDS;

		$bCanWrite = (bool)$this->getOpt( 'ptg_candiskwrite' ) && !$bLastCheckExpired;
		if ( !$bCanWrite ) {
			$oFS = Services::WpFs();
			$sDir = $this->getPtgSnapsBaseDir();

			if ( $sDir && $oFS->mkdir( $sDir ) ) {
				$sTestFile = path_join( $sDir, 'test.txt' );
				$oFS->putFileContent( $sTestFile, 'test-'.$nNow );
				$sContents = $oFS->exists( $sTestFile ) ? $oFS->getFileContent( $sTestFile ) : '';
				if ( $sContents === 'test-'.$nNow ) {
					$oFS->deleteFile( $sTestFile );
					$this->setOpt( 'ptg_candiskwrite', !$oFS->exists( $sTestFile ) );
				}
				$this->setOpt( 'ptg_candiskwrite_at', $nNow );
			}
		}

		return $bCanWrite;
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
	 * @return string|false
	 */
	public function getPtgSnapsBaseDir() {
		try {
			$sPath = $this->getCon()->getPluginCachePath( 'ptguard/' );
		}
		catch ( \Exception $oE ) {
			$sPath = false;
		}
		return $sPath;
	}

	/**
	 * @return bool
	 */
	public function isPtgBuildRequired() {
		return $this->isPtgEnabled() && ( $this->getPtgLastBuildAt() == 0 );
	}

	/**
	 * @param bool $bIsRequired
	 * @return $this
	 */
	public function setPtgRebuildSelfRequired( $bIsRequired ) {
		return $this->setOpt( 'rebuild_self', (bool)$bIsRequired );
	}

	/**
	 * @param bool $bIsRequired
	 * @return $this
	 */
	public function setPtgUpdateStoreFormat( $bIsRequired ) {
		return $this->setOpt( 'ptg_update_store_format', (bool)$bIsRequired );
	}

	/**
	 * @return bool
	 */
	public function isPtgRebuildSelfRequired() {
		return $this->isOpt( 'rebuild_self', true );
	}

	/**
	 * @return bool
	 */
	public function isPtgUpdateStoreFormat() {
		return $this->isOpt( 'ptg_update_store_format', true );
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
	 * @param int $nTime
	 * @return $this
	 */
	public function setPtgLastBuildAt( $nTime = null ) {
		return $this->setOpt( 'ptg_last_build_at', is_null( $nTime ) ? Services::Request()->ts() : $nTime );
	}

	/**
	 * @param string $sValue
	 * @return $this
	 */
	public function setPtgEnabledOption( $sValue ) {
		return $this->setOpt( 'ptg_enable', $sValue );
	}

	/**
	 * @return bool
	 */
	public function isApcEnabled() {
		return !$this->isOpt( 'enabled_scan_apc', 'disabled' );
	}

	/**
	 * @return bool
	 */
	public function isApcSendEmail() {
		return $this->isOpt( 'enabled_scan_apc', 'enabled_email' );
	}

	/**
	 * @return bool
	 */
	public function isMalScanEnabled() {
		return !$this->isOpt( 'mal_scan_enable', 'disabled' );
	}

	/**
	 * @return bool
	 */
	public function isMalAutoRepairCore() {
		return $this->isOpt( 'mal_autorepair_core', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isMalAutoRepairPlugins() {
		return $this->isOpt( 'mal_autorepair_plugins', 'Y' );
	}

	public function insertCustomJsVars_Admin() {
		parent::insertCustomJsVars_Admin();

		if ( Services::WpPost()->isCurrentPage( 'plugins.php' ) && $this->isPtgReinstallLinks() ) {
			wp_localize_script(
				$this->prefix( 'global-plugin' ),
				'icwp_wpsf_vars_hp',
				[
					'ajax_plugin_reinstall' => $this->getAjaxActionData( 'plugin_reinstall' ),
					'reinstallable'         => Services::WpPlugins()->getInstalledWpOrgPluginFiles()
				]
			);
			wp_enqueue_script( 'jquery-ui-dialog' ); // jquery and jquery-ui should be dependencies, didn't check though...
			wp_enqueue_style( 'wp-jquery-ui-dialog' );
		}
	}

	/**
	 * @param string $sSectionSlug
	 * @return array
	 */
	protected function getSectionNotices( $sSectionSlug ) {
		$aNotices = [];
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

			case 'section_scan_malware':
				$nTime = $this->getLastScanAt( 'mal' );
				break;

			default:
				$nTime = null;
				break;
		}

		if ( !is_null( $nTime ) ) {
			$nTime = ( $nTime > 0 ) ? Services::WpGeneral()
											  ->getTimeStampForDisplay( $nTime ) : __( 'Never', 'wp-simple-firewall' );
			$aNotices[] = sprintf( __( 'Last Scan Time: %s', 'wp-simple-firewall' ), $nTime );
		}
		return $aNotices;
	}

	/**
	 * @return array
	 */
	protected function ajaxExec_BuildTableScan() {

		switch ( Services::Request()->post( 'fScan' ) ) {

			case 'apc':
				$oTableBuilder = new Shield\Tables\Build\ScanApc();
				break;

			case 'mal':
				$oTableBuilder = new Shield\Tables\Build\ScanMal();
				break;

			case 'wcf':
				$oTableBuilder = new Shield\Tables\Build\ScanWcf();
				break;

			case 'ptg':
				$oTableBuilder = new Shield\Tables\Build\ScanPtg();
				break;

			case 'ufc':
				$oTableBuilder = new Shield\Tables\Build\ScanUfc();
				break;

			case 'wpv':
				$oTableBuilder = new Shield\Tables\Build\ScanWpv();
				break;

			default:
				break;
		}

		if ( empty( $oTableBuilder ) ) {
			$sHtml = 'SCAN SLUG NOT SPECIFIED';
		}
		else {
			/** @var ICWP_WPSF_Processor_HackProtect $oPro */
			$oPro = $this->getProcessor();
			$sHtml = $oTableBuilder
				->setMod( $this )
				->setDbHandler( $oPro->getSubProScanner()->getDbHandler() )
				->buildTable();
		}

		return [
			'success' => !empty( $oTableBuilder ),
			'html'    => $sHtml
		];
	}

	/**
	 * @return array
	 */
	private function ajaxExec_StartScans() {
		$bSuccess = false;
		$bPageReload = false;
		$sMessage = __( 'No scans were selected', 'wp-simple-firewall' );
		$aFormParams = $this->getAjaxFormParams();

		if ( !empty( $aFormParams ) ) {
			foreach ( array_keys( $aFormParams ) as $sScan ) {

				$oTablePro = $this->getScannerFromSlug( $sScan );

				if ( !empty( $oTablePro ) && $oTablePro->isEnabled() ) {
					$oTablePro->doScan();

					if ( isset( $aFormParams[ 'opt_clear_ignore' ] ) ) {
						$oTablePro->resetIgnoreStatus();
					}
					if ( isset( $aFormParams[ 'opt_clear_notification' ] ) ) {
						$oTablePro->resetNotifiedStatus();
					}

					$bSuccess = true;
					$bPageReload = true;
					$sMessage = __( 'Scans completed.', 'wp-simple-firewall' ).' '.__( 'Reloading page', 'wp-simple-firewall' ).'...';
				}
			}
		}

		return [
			'success'     => $bSuccess,
			'page_reload' => $bPageReload,
			'message'     => $sMessage,
		];
	}

	/**
	 * @param string $sAction
	 * @return array
	 */
	private function ajaxExec_ScanItemAction( $sAction ) {
		$oReq = Services::Request();

		$bSuccess = false;

		$sItemId = $oReq->post( 'rid' );
		$aItemIds = $oReq->post( 'ids' );
		$sScannerSlug = $oReq->post( 'fScan' );

		$oTablePro = $this->getScannerFromSlug( $sScannerSlug );

		if ( empty( $oTablePro ) ) {
			$sMessage = __( 'Unsupported scanner', 'wp-simple-firewall' );
		}
		else if ( empty( $sItemId ) && ( empty( $aItemIds ) || !is_array( $aItemIds ) ) ) {
			$sMessage = __( 'Unsupported item(s) selected', 'wp-simple-firewall' );
		}
		else {
			if ( empty( $aItemIds ) ) {
				$aItemIds = [ $sItemId ];
			}

			try {
				$aSuccessfulItems = [];

				foreach ( $aItemIds as $sId ) {
					if ( $oTablePro->executeItemAction( $sId, $sAction ) ) {
						$aSuccessfulItems[] = $sId;
					}
				}

				if ( count( $aSuccessfulItems ) === count( $aItemIds ) ) {
					$bSuccess = true;
					$sMessage = 'Successfully completed. Re-scanning and reloading ...';
				}
				else {
					$sMessage = 'An error occurred - not all items may have been processed. Re-scanning and reloading ...';
				}
				$oTablePro->doScan();
			}
			catch ( \Exception $oE ) {
				$sMessage = $oE->getMessage();
			}
		}

		return [
			'success'     => $bSuccess,
			'page_reload' => in_array( $sScannerSlug, [ 'apc', 'ptg' ] ),
			'message'     => $sMessage,
		];
	}

	/**
	 * @param string $sSlug
	 * @return ICWP_WPSF_Processor_ScanBase|null
	 */
	private function getScannerFromSlug( $sSlug ) {
		/** @var ICWP_WPSF_Processor_HackProtect $oP */
		$oP = $this->getProcessor();
		$oScanPro = $oP->getSubProScanner();
		switch ( $sSlug ) {
			case 'apc':
				$oScannerPro = $oScanPro->getSubProcessorApc();
				break;
			case 'mal':
				$oScannerPro = $oScanPro->getSubProcessorMal();
				break;
			case 'ptg':
				$oScannerPro = $oScanPro->getSubProcessorPtg();
				break;
			case 'ufc':
				$oScannerPro = $oScanPro->getSubProcessorUfc();
				break;
			case 'wcf':
				$oScannerPro = $oScanPro->getSubProcessorWcf();
				break;
			case 'wpv':
				$oScannerPro = $oScanPro->getSubProcessorWpv();
				break;
			default:
				$oScannerPro = null;
				break;
		}

		return $oScannerPro;
	}

	/**
	 * @param string $sSection
	 * @return array
	 */
	protected function getSectionWarnings( $sSection ) {
		$aWarnings = [];

		switch ( $sSection ) {

			case 'section_pluginthemes_guard':
				if ( !$this->canPtgWriteToDisk() ) {
					$aWarnings[] = sprintf( __( 'Sorry, this feature is not available because we cannot write to disk at this location: "%s"', 'wp-simple-firewall' ), $this->getPtgSnapsBaseDir() );
				}
				break;

			case 'section_realtime':
				if ( !Services::Encrypt()->isSupportedOpenSslDataEncryption() ) {
					$aWarnings[] = sprintf( __( 'Not available because the %s extension is not available.', 'wp-simple-firewall' ), 'OpenSSL' );
				}
				if ( !Services::WpFs()->isFilesystemAccessDirect() ) {
					$aWarnings[] = sprintf( __( "Not available because PHP/WordPress doesn't have direct filesystem access.", 'wp-simple-firewall' ), 'OpenSSL' );
				}
				else {
					$sPath = $this->getRtMapFileKeyToFilePath( 'wpconfig' );
					if ( !$this->getRtCanWriteFile( $sPath ) ) {
						$aWarnings[] = sprintf( __( "The %s file isn't writable and so can't be further protected.", 'wp-simple-firewall' ), 'wp-config.php' );
					}
				}
				break;
		}

		return $aWarnings;
	}

	/**
	 * temporary
	 * @return bool
	 */
	public function hasWizard() {
		return false;
	}

	/**
	 * cleans out any reference to any backup files
	 */
	private function resetRtBackupFiles() {
		$oCon = $this->getCon();
		$oFs = Services::WpFs();
		$oOpts = $this->getOptionsVo();
		foreach ( [ 'htaccess', 'wpconfig' ] as $sFileKey ) {
			if ( $oOpts->isOptChanged( 'rt_file_'.$sFileKey ) ) {
				$sPath = $this->getRtMapFileKeyToFilePath( $sFileKey );
				try {
					$sBackupFile = $oCon->getPluginCachePath( $this->getRtFileBackupName( $sPath ) );
					if ( $oFs->exists( $sBackupFile ) ) {
						$oFs->deleteFile( $sBackupFile );
					}

					if ( !$this->getRtCanWriteFile( $sPath ) ) {
						$this->setOpt( 'rt_file_'.$sFileKey, 'N' );
					}
				}
				catch ( \Exception $oE ) {
				}
				$this->setRtFileHash( $sPath, '' )
					 ->setRtFileBackupName( $sPath, '' );
			}
		}
	}

	/**
	 * @param string $sKey
	 * @return string|null
	 */
	private function getRtMapFileKeyToFilePath( $sKey ) {
		$aMap = [
			'wpconfig' => Services::WpGeneral()->getPath_WpConfig(),
			'htaccess' => path_join( ABSPATH, '.htaccess' ),
		];
		return isset( $aMap[ $sKey ] ) ? $aMap[ $sKey ] : null;
	}

	/**
	 * @return array
	 */
	public function getRtFileBackupNames() {
		$aF = $this->getOpt( 'rt_file_backup_names', [] );
		return is_array( $aF ) ? $aF : [];
	}

	/**
	 * @param string $sFile
	 * @return string|null
	 */
	public function getRtFileBackupName( $sFile ) {
		$aD = $this->getRtFileBackupNames();
		return isset( $aD[ $sFile ] ) ? $aD[ $sFile ] : null;
	}

	/**
	 * @return array
	 */
	public function getRtFileHashes() {
		$aF = $this->getOpt( 'rt_file_hashes', [] );
		return is_array( $aF ) ? $aF : [];
	}

	/**
	 * @param string $sFile
	 * @return string|null
	 */
	public function getRtFileHash( $sFile ) {
		$aD = $this->getRtFileHashes();
		return isset( $aD[ $sFile ] ) ? $aD[ $sFile ] : null;
	}

	/**
	 * @param string $sFile
	 * @param string $sName
	 * @return $this
	 */
	public function setRtFileBackupName( $sFile, $sName ) {
		$aD = $this->getRtFileBackupNames();
		$aD[ $sFile ] = $sName;
		return $this->setOpt( 'rt_file_backup_names', $aD );
	}

	/**
	 * @param string $sFile
	 * @param string $sHash
	 * @return $this
	 */
	public function setRtFileHash( $sFile, $sHash ) {
		$aD = $this->getRtFileHashes();
		$aD[ $sFile ] = $sHash;
		return $this->setOpt( 'rt_file_hashes', $aD );
	}

	/**
	 * @return array
	 */
	public function getRtCanWriteFiles() {
		$aF = $this->getOpt( 'rt_can_write_files', [] );
		return is_array( $aF ) ? $aF : [];
	}

	/**
	 * @param string $sFile
	 * @return bool
	 */
	public function getRtCanWriteFile( $sFile ) {
		$aFiles = $this->getRtCanWriteFiles();
		if ( isset( $aFiles[ $sFile ] ) ) {
			$bCanWrite = $aFiles[ $sFile ] > 0;
		}
		else {
			$bCanWrite = ( new Shield\Scans\Realtime\Files\TestWritable() )->run( $sFile );
			$this->setRtCanWriteFile( $sFile, $bCanWrite );
		}
		return $bCanWrite;
	}

	/**
	 * @return bool
	 */
	public function isRtAvailable() {
		return $this->isPremium()
			   && Services::WpFs()->isFilesystemAccessDirect()
			   && Services::Encrypt()->isSupportedOpenSslDataEncryption();
	}

	/**
	 * @return bool
	 */
	public function isRtEnabledWpConfig() {
		return $this->isRtAvailable() && $this->isOpt( 'rt_file_wpconfig', 'Y' )
			   && $this->getRtCanWriteFile( $this->getRtMapFileKeyToFilePath( 'wpconfig' ) );
	}

	/**
	 * @param string $sPath
	 * @param bool   $bCanWrite
	 * @return $this
	 */
	public function setRtCanWriteFile( $sPath, $bCanWrite ) {
		$aFiles = $this->getRtCanWriteFiles();
		$aFiles[ $sPath ] = $bCanWrite ? Services::Request()->ts() : 0;
		return $this->setOpt( 'rt_can_write_files', $aFiles );
	}

	/**
	 * @param array $aAllNotices
	 * @return array
	 */
	public function addInsightsNoticeData( $aAllNotices ) {
		$aNotices = [
			'title'    => __( 'Scans', 'wp-simple-firewall' ),
			'messages' => []
		];

		{// Core files
			if ( !$this->isWcfScanEnabled() ) {
				$aNotices[ 'messages' ][ 'wcf' ] = [
					'title'   => 'WP Core Files',
					'message' => __( 'Core File scanner is not enabled.', 'wp-simple-firewall' ),
					'href'    => $this->getUrl_DirectLinkToSection( 'section_core_file_integrity_scan' ),
					'action'  => sprintf( 'Go To %s', __( 'Options', 'wp-simple-firewall' ) ),
					'rec'     => __( 'Automatic WordPress Core File scanner should be turned-on.', 'wp-simple-firewall' )
				];
			}
			else if ( $this->getScanHasProblem( 'wcf' ) ) {
				$aNotices[ 'messages' ][ 'wcf' ] = [
					'title'   => 'WP Core Files',
					'message' => __( 'Modified WordPress core files found.', 'wp-simple-firewall' ),
					'href'    => $this->getUrlManualScan(),
					'action'  => __( 'Run Scan', 'wp-simple-firewall' ),
					'rec'     => __( 'Scan WP core files and repair any files that are flagged as modified.', 'wp-simple-firewall' )
				];
			}
		}

		{// Unrecognised
			if ( !$this->isUfcEnabled() ) {
				$aNotices[ 'messages' ][ 'ufc' ] = [
					'title'   => 'Unrecognised Files',
					'message' => __( 'Unrecognised File scanner is not enabled.', 'wp-simple-firewall' ),
					'href'    => $this->getUrl_DirectLinkToSection( 'section_unrecognised_file_scan' ),
					'action'  => sprintf( 'Go To %s', __( 'Options', 'wp-simple-firewall' ) ),
					'rec'     => __( 'Automatic scanning for non-WordPress core files is recommended.', 'wp-simple-firewall' )
				];
			}
			else if ( $this->getScanHasProblem( 'ufc' ) ) {
				$aNotices[ 'messages' ][ 'ufc' ] = [
					'title'   => 'Unrecognised Files',
					'message' => __( 'Unrecognised files found in WordPress Core directory.', 'wp-simple-firewall' ),
					'href'    => $this->getUrlManualScan(),
					'action'  => __( 'Run Scan', 'wp-simple-firewall' ),
					'rec'     => __( 'Scan and remove any files that are not meant to be in the WP core directories.', 'wp-simple-firewall' )
				];
			}
		}

		{// Plugin/Theme Guard
			if ( !$this->isPtgEnabled() ) {
				$aNotices[ 'messages' ][ 'ptg' ] = [
					'title'   => 'Plugin/Theme Guard',
					'message' => __( 'Automatic Plugin/Themes Guard is not enabled.', 'wp-simple-firewall' ),
					'href'    => $this->getUrl_DirectLinkToSection( 'section_pluginthemes_guard' ),
					'action'  => sprintf( 'Go To %s', __( 'Options', 'wp-simple-firewall' ) ),
					'rec'     => __( 'Automatic detection of plugin/theme modifications is recommended.', 'wp-simple-firewall' )
				];
			}
			else if ( $this->getScanHasProblem( 'ptg' ) ) {
				$aNotices[ 'messages' ][ 'ptg' ] = [
					'title'   => 'Plugin/Theme Guard',
					'message' => __( 'A plugin/theme was found to have been modified.', 'wp-simple-firewall' ),
					'href'    => $this->getUrlManualScan(),
					'action'  => __( 'Run Scan', 'wp-simple-firewall' ),
					'rec'     => __( 'Reviewing modifications to your plugins/themes is recommended.', 'wp-simple-firewall' )
				];
			}
		}

		{// Vulnerability Scanner
			if ( !$this->isWpvulnEnabled() ) {
				$aNotices[ 'messages' ][ 'wpv' ] = [
					'title'   => 'Vulnerability Scanner',
					'message' => __( 'Vulnerability Scanner is not enabled.', 'wp-simple-firewall' ),
					'href'    => $this->getUrl_DirectLinkToSection( 'section_wpvuln_scan' ),
					'action'  => sprintf( 'Go To %s', __( 'Options', 'wp-simple-firewall' ) ),
					'rec'     => __( 'Automatic detection of vulnerabilities is recommended.', 'wp-simple-firewall' )
				];
			}
			else if ( $this->getScanHasProblem( 'wpv' ) ) {
				$aNotices[ 'messages' ][ 'wpv' ] = [
					'title'   => 'Vulnerable Items',
					'message' => __( 'At least 1 item has known vulnerabilities.', 'wp-simple-firewall' ),
					'href'    => $this->getUrlManualScan(),
					'action'  => __( 'Run Scan', 'wp-simple-firewall' ),
					'rec'     => __( 'Items with known vulnerabilities should be updated, removed, or replaced.', 'wp-simple-firewall' )
				];
			}
		}

		{// Abandoned Plugins
			if ( !$this->isApcEnabled() ) {
				$aNotices[ 'messages' ][ 'apc' ] = [
					'title'   => 'Abandoned Plugins Scanner',
					'message' => __( 'Abandoned Plugins Scanner is not enabled.', 'wp-simple-firewall' ),
					'href'    => $this->getUrl_DirectLinkToSection( 'section_scan_apc' ),
					'action'  => sprintf( 'Go To %s', __( 'Options', 'wp-simple-firewall' ) ),
					'rec'     => __( 'Automatic detection of abandoned plugins is recommended.', 'wp-simple-firewall' )
				];
			}
			else if ( $this->getScanHasProblem( 'apc' ) ) {
				$aNotices[ 'messages' ][ 'apc' ] = [
					'title'   => 'Abandoned Plugins',
					'message' => __( 'At least 1 plugin on your site is abandoned.', 'wp-simple-firewall' ),
					'href'    => $this->getUrlManualScan(),
					'action'  => __( 'Run Scan', 'wp-simple-firewall' ),
					'rec'     => __( 'Plugins that have been abandoned represent a potential risk to your site.', 'wp-simple-firewall' )
				];
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
		$aThis = [
			'strings'      => [
				'title' => __( 'Hack Guard', 'wp-simple-firewall' ),
				'sub'   => __( 'Threats/Intrusions Detection & Repair', 'wp-simple-firewall' ),
			],
			'key_opts'     => [],
			'href_options' => $this->getUrl_AdminPage()
		];

		if ( !$this->isModOptEnabled() ) {
			$aThis[ 'key_opts' ][ 'mod' ] = $this->getModDisabledInsight();
		}
		else {
			$bGoodFrequency = $this->getScanFrequency() > 1;
			$aThis[ 'key_opts' ][ 'frequency' ] = [
				'name'    => __( 'Scan Frequency', 'wp-simple-firewall' ),
				'enabled' => $bGoodFrequency,
				'summary' => $bGoodFrequency ?
					__( 'Automatic scanners run more than once per day', 'wp-simple-firewall' )
					: __( "Automatic scanners only run once per day", 'wp-simple-firewall' ),
				'weight'  => 1,
				'href'    => $this->getUrl_DirectLinkToSection( 'section_scan_options' ),
			];

			$bCore = $this->isWcfScanEnabled();
			$aThis[ 'key_opts' ][ 'wcf' ] = [
				'name'    => __( 'WP Core File Scan', 'wp-simple-firewall' ),
				'enabled' => $bCore,
				'summary' => $bCore ?
					__( 'Core files scanned regularly for hacks', 'wp-simple-firewall' )
					: __( "Core files are never scanned for hacks!", 'wp-simple-firewall' ),
				'weight'  => 2,
				'href'    => $this->getUrl_DirectLinkToSection( 'section_core_file_integrity_scan' ),
			];
			if ( $bCore && !$this->isWcfScanAutoRepair() ) {
				$aThis[ 'key_opts' ][ 'wcf_repair' ] = [
					'name'    => __( 'WP Core File Repair', 'wp-simple-firewall' ),
					'enabled' => $this->isWcfScanAutoRepair(),
					'summary' => $this->isWcfScanAutoRepair() ?
						__( 'Core files are automatically repaired', 'wp-simple-firewall' )
						: __( "Core files aren't automatically repaired!", 'wp-simple-firewall' ),
					'weight'  => 1,
					'href'    => $this->getUrl_DirectLinkToSection( 'section_core_file_integrity_scan' ),
				];
			}

			$bUcf = $this->isUfcEnabled();
			$aThis[ 'key_opts' ][ 'ufc' ] = [
				'name'    => __( 'Unrecognised Files', 'wp-simple-firewall' ),
				'enabled' => $bUcf,
				'summary' => $bUcf ?
					__( 'Core directories scanned regularly for unrecognised files', 'wp-simple-firewall' )
					: __( "WP Core is never scanned for unrecognised files!", 'wp-simple-firewall' ),
				'weight'  => 2,
				'href'    => $this->getUrl_DirectLinkToSection( 'section_unrecognised_file_scan' ),
			];
			if ( $bUcf && !$this->isUfcDeleteFiles() ) {
				$aThis[ 'key_opts' ][ 'ufc_repair' ] = [
					'name'    => __( 'Unrecognised Files Removal', 'wp-simple-firewall' ),
					'enabled' => $this->isUfcDeleteFiles(),
					'summary' => $this->isUfcDeleteFiles() ?
						__( 'Unrecognised files are automatically removed', 'wp-simple-firewall' )
						: __( "Unrecognised files aren't automatically removed!", 'wp-simple-firewall' ),
					'weight'  => 1,
					'href'    => $this->getUrl_DirectLinkToSection( 'section_unrecognised_file_scan' ),
				];
			}

			$bWpv = $this->isWpvulnEnabled();
			$aThis[ 'key_opts' ][ 'wpv' ] = [
				'name'    => __( 'Vulnerability Scan', 'wp-simple-firewall' ),
				'enabled' => $bWpv,
				'summary' => $bWpv ?
					__( 'Regularly scanning for known vulnerabilities', 'wp-simple-firewall' )
					: __( "Plugins/Themes never scanned for vulnerabilities!", 'wp-simple-firewall' ),
				'weight'  => 2,
				'href'    => $this->getUrl_DirectLinkToSection( 'section_wpvuln_scan' ),
			];
			if ( $bWpv && !$this->isWpvulnAutoupdatesEnabled() ) {
				$aThis[ 'key_opts' ][ 'wpv_repair' ] = [
					'name'    => __( 'Auto Update', 'wp-simple-firewall' ),
					'enabled' => $this->isWpvulnAutoupdatesEnabled(),
					'summary' => $this->isWpvulnAutoupdatesEnabled() ?
						__( 'Vulnerable items are automatically updated', 'wp-simple-firewall' )
						: __( "Vulnerable items aren't automatically updated!", 'wp-simple-firewall' ),
					'weight'  => 1,
					'href'    => $this->getUrl_DirectLinkToSection( 'section_wpvuln_scan' ),
				];
			}

			$bPtg = $this->isPtgEnabled();
			$aThis[ 'key_opts' ][ 'ptg' ] = [
				'name'    => __( 'Plugin/Theme Guard', 'wp-simple-firewall' ),
				'enabled' => $bPtg,
				'summary' => $bPtg ?
					__( 'Plugins and Themes are guarded against tampering', 'wp-simple-firewall' )
					: __( "Plugins and Themes are never scanned for tampering!", 'wp-simple-firewall' ),
				'weight'  => 2,
				'href'    => $this->getUrl_DirectLinkToSection( 'section_pluginthemes_guard' ),
			];
		}

		$aAllData[ $this->getSlug() ] = $aThis;
		return $aAllData;
	}

	/**
	 * TODO: build better/dynamic direct linking to insights sub-pages
	 */
	public function getUrlManualScan() {
		return add_query_arg(
			[ 'inav' => 'scans' ],
			$this->getCon()->getModule( 'insights' )->getUrl_AdminPage()
		);
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws \Exception
	 */
	protected function loadStrings_SectionTitles( $aOptionsParams ) {

		$sSectionSlug = $aOptionsParams[ 'slug' ];
		switch ( $sSectionSlug ) {

			case 'section_scan_options' :
				$sTitle = __( 'Scan Options', 'wp-simple-firewall' );
				$sTitleShort = __( 'Scan Options', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Set how frequently the Hack Guard scans will run.', 'wp-simple-firewall' ) )
				];
				break;

			case 'section_realtime' :
				$sTitleShort = __( 'Realtime', 'wp-simple-firewall' );
				$sTitle = __( 'Realtime Site Protection', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Provides realtime protection for certain key files.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Keep realtime protection turned on to protect key files.', 'wp-simple-firewall' ) ),
				];
				break;

			case 'section_enable_plugin_feature_hack_protection_tools' :
				$sTitleShort = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$sTitle = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Hack Guard is a set of tools to warn you and protect you against hacks on your site.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'Hack Guard', 'wp-simple-firewall' ) ) )
				];
				break;

			case 'section_wpvuln_scan' :
				$sTitleShort = __( 'Vulnerabilities', 'wp-simple-firewall' );
				$sTitle = __( 'Vulnerabilities Scanner', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Regularly scan your WordPress plugins and themes for known security vulnerabilities.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'Vulnerabilities Scanner', 'wp-simple-firewall' ) ) ),
					__( 'Ensure this is turned on and you will always know if any of your assets have known security vulnerabilities.', 'wp-simple-firewall' )
				];
				break;

			case 'section_core_file_integrity_scan' :
				$sTitleShort = __( 'Core Files', 'wp-simple-firewall' );
				$sTitle = __( 'WordPress Core File Scanner', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Regularly scan your WordPress core files for changes compared to official WordPress files.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), $sTitle ) )
				];
				break;

			case 'section_unrecognised_file_scan' :
				$sTitleShort = __( 'Unrecognised Files', 'wp-simple-firewall' );
				$sTitle = __( 'Unrecognised Files Scanner', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( "Regularly scan your WordPress core folders for files that don't belong.", 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), $sTitle ) )
				];
				break;

			case 'section_scan_apc' :
				$sTitle = __( 'Enable The Abandoned Plugin Scanner', 'wp-simple-firewall' );
				$sTitleShort = __( 'Abandoned Plugins', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ),
						__( 'Monitor your site for plugins that have been abandoned by their authors and are no longer maintained.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Enable this to alert you to your site running unmaintained code.', 'wp-simple-firewall' ) )
				];
				break;

			case 'section_pluginthemes_guard' :
				$sTitle = __( 'Plugins and Themes Guard', 'wp-simple-firewall' );
				$sTitleShort = __( 'Plugins/Themes Guard', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Detect malicious changes to your themes and plugins.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Keep the Plugins/Theme Guard feature turned on.', 'wp-simple-firewall' ) ),
				];
				break;

			case 'section_scan_malware' :
				$sTitleShort = __( 'Malware', 'wp-simple-firewall' );
				$sTitle = __( 'Malware Scanner', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Monitor and detect presence of Malware signatures.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Enable this scanner to automatically detect infected files.', 'wp-simple-firewall' ) )
				];
				break;

			case 'section_integrity_checking' :
				$sTitle = __( 'Integrity Checks', 'wp-simple-firewall' );
				$sTitleShort = __( 'Integrity Checks', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Monitor for unrecognised changes to your system.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Enable these to prevent unauthorized changes to your WordPress site.', 'wp-simple-firewall' ) )
				];
				break;

			default:
				throw new \Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
		}
		$aOptionsParams[ 'title' ] = $sTitle;
		$aOptionsParams[ 'summary' ] = ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : [];
		$aOptionsParams[ 'title_short' ] = $sTitleShort;
		return $aOptionsParams;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws \Exception
	 */
	protected function loadStrings_Options( $aOptionsParams ) {
		$sKey = $aOptionsParams[ 'key' ];
		switch ( $sKey ) {

			case 'enable_hack_protect' :
				$sName = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				$sSummary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				$sDescription = sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				break;

			case 'scan_frequency' :
				$sName = __( 'Daily Scan Frequency', 'wp-simple-firewall' );
				$sSummary = __( 'Number Of Times To Automatically Run File Scan In 24hrs', 'wp-simple-firewall' );
				$sDescription = sprintf( '%s: %s', __( 'Default', 'wp-simple-firewall' ), __( 'Once every 24hrs.', 'wp-simple-firewall' ) )
								.'<br/>'.__( 'To improve security, increase the number of scans per day.', 'wp-simple-firewall' );
				break;

			case 'notification_interval' :
				$sName = __( 'Repeat Notifications', 'wp-simple-firewall' );
				$sSummary = __( 'Item Repeat Notifications Suppression Interval', 'wp-simple-firewall' );
				$sDescription = __( 'How long the automated scans should wait before repeating a notification about an item.', 'wp-simple-firewall' )
								.'<br/>'.__( 'Specify the number of days to suppress repeat notifications.', 'wp-simple-firewall' )
								.'<br/>'.sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), __( 'This is per discovered item or file, not per scan.', 'wp-simple-firewall' ) );
				break;

			case 'email_files_list' :
				$sName = __( 'Email Files List', 'wp-simple-firewall' );
				$sSummary = __( 'Scan Notification Emails Should Include Full Listing Of Files', 'wp-simple-firewall' );
				$sDescription = __( 'Scanner notification emails will include a summary list of all affected files.', 'wp-simple-firewall' );
				break;

			case 'enable_plugin_vulnerabilities_scan' :
				$sName = __( 'Vulnerabilities Scanner', 'wp-simple-firewall' );
				$sSummary = sprintf( __( 'Daily Cron - %s', 'wp-simple-firewall' ), __( 'Scans Plugins For Known Vulnerabilities', 'wp-simple-firewall' ) );
				$sDescription = __( 'Runs a scan of all your plugins against a database of known WordPress plugin vulnerabilities.', 'wp-simple-firewall' );
				break;

			case 'enable_wpvuln_scan' :
				$sName = __( 'Vulnerability Scanner', 'wp-simple-firewall' );
				$sSummary = __( 'Enable The Vulnerability Scanner', 'wp-simple-firewall' );
				$sDescription = __( 'Runs a scan of all your plugins against a database of known WordPress vulnerabilities.', 'wp-simple-firewall' );
				break;

			case 'wpvuln_scan_autoupdate' :
				$sName = __( 'Automatic Updates', 'wp-simple-firewall' );
				$sSummary = __( 'Apply Updates Automatically To Vulnerable Plugins', 'wp-simple-firewall' );
				$sDescription = __( 'When an update becomes available, automatically apply updates to items with known vulnerabilities.', 'wp-simple-firewall' );
				break;

			case 'wpvuln_scan_display' :
				$sName = __( 'Highlight Plugins', 'wp-simple-firewall' );
				$sSummary = __( 'Highlight Vulnerable Plugins Upon Display', 'wp-simple-firewall' );
				$sDescription = __( 'Vulnerable plugins will be highlighted on the main plugins page.', 'wp-simple-firewall' );
				break;

			case 'enable_core_file_integrity_scan' :
				$sName = __( 'WP Core File Scanner', 'wp-simple-firewall' );
				$sSummary = __( 'Automatically Scans WordPress Core Files For Changes', 'wp-simple-firewall' );
				$sDescription = __( 'Compares all WordPress core files on your site against the official WordPress files.', 'wp-simple-firewall' )
								.'<br />'.__( 'WordPress Core files should never be altered for any reason.', 'wp-simple-firewall' );
				break;

			case 'attempt_auto_file_repair' :
				$sName = __( 'Auto Repair', 'wp-simple-firewall' );
				$sSummary = __( 'Automatically Repair WordPress Core Files That Have Been Altered', 'wp-simple-firewall' );
				$sDescription = __( 'Attempts to automatically repair WordPress Core files with the official WordPress file data, for files that have been altered or are missing.', 'wp-simple-firewall' );
				break;

			case 'enable_unrecognised_file_cleaner_scan' :
				$sName = __( 'Unrecognised Files Scanner', 'wp-simple-firewall' );
				$sSummary = __( 'Automatically Scans For Unrecognised Files In Core Directories', 'wp-simple-firewall' );
				$sDescription = __( 'Scans for, and automatically deletes, any files in your core WordPress folders that are not part of your WordPress installation.', 'wp-simple-firewall' );
				break;

			case 'ufc_scan_uploads' :
				$sName = __( 'Scan Uploads', 'wp-simple-firewall' );
				$sSummary = __( 'Scan Uploads Folder For PHP and Javascript', 'wp-simple-firewall' );
				$sDescription = sprintf( '%s - %s', __( 'Warning', 'wp-simple-firewall' ), __( 'Take care when turning on this option - if you are unsure, leave it disabled.', 'wp-simple-firewall' ) )
								.'<br />'.__( 'The Uploads folder is primarily for media, but could be used to store nefarious files.', 'wp-simple-firewall' );
				break;

			case 'ufc_exclusions' :
				$sName = __( 'File Exclusions', 'wp-simple-firewall' );
				$sSummary = __( 'Provide A List Of Files To Be Excluded From The Scan', 'wp-simple-firewall' );
				$sDefaults = implode( ', ', $this->getOptionsVo()->getOptDefault( 'ufc_exclusions' ) );
				$sDescription = __( 'Take a new line for each file you wish to exclude from the scan.', 'wp-simple-firewall' )
								.'<br/><strong>'.__( 'No commas are necessary.', 'wp-simple-firewall' ).'</strong>'
								.'<br/>'.sprintf( '%s: %s', __( 'Default', 'wp-simple-firewall' ), $sDefaults );
				break;

			case 'ic_enabled' :
				$sName = __( 'Enable Integrity Scan', 'wp-simple-firewall' );
				$sSummary = __( 'Scans For Critical Changes Made To Your WordPress Site', 'wp-simple-firewall' );
				$sDescription = __( 'Detects changes made to your WordPress site outside of WordPress.', 'wp-simple-firewall' );
				break;

			case 'ic_users' :
				$sName = __( 'Monitor User Accounts', 'wp-simple-firewall' );
				$sSummary = __( 'Scans For Critical Changes Made To User Accounts', 'wp-simple-firewall' );
				$sDescription = sprintf( __( 'Detects changes made to critical user account information that were made directly on the database and outside of the WordPress system.', 'wp-simple-firewall' ), 'author=' )
								.'<br />'.__( 'An example of this might be some form of SQL Injection attack.', 'wp-simple-firewall' )
								.'<br />'.sprintf( '%s: %s', __( 'Warning', 'wp-simple-firewall' ), __( 'Enabling this option for every page low may slow down your site with large numbers of users.', 'wp-simple-firewall' ) )
								.'<br />'.sprintf( '%s: %s', __( 'Warning', 'wp-simple-firewall' ), __( 'This option may cause critical problem with 3rd party plugins that manage user accounts.', 'wp-simple-firewall' ) );
				break;

			case 'ptg_enable' :
				$sName = sprintf( __( 'Enable %s', 'wp-simple-firewall' ), __( 'Guard', 'wp-simple-firewall' ) );
				$sSummary = __( 'Enable The Guard For Plugin And Theme Files', 'wp-simple-firewall' );
				$sDescription = __( 'When enabled the Guard will automatically scan for changes to your Plugin and Theme files.', 'wp-simple-firewall' );
				break;

			case 'ptg_depth' :
				$sName = __( 'Guard/Scan Depth', 'wp-simple-firewall' );
				$sSummary = __( 'How Deep Into The Plugin Directories To Scan And Guard', 'wp-simple-firewall' );
				$sDescription = __( 'The Guard normally scans only the top level of a folder. Increasing depth will increase scan times.', 'wp-simple-firewall' )
								.'<br/>'.sprintf( __( 'Setting it to %s will remove this limit and all sub-folders will be scanned - not recommended', 'wp-simple-firewall' ), 0 );
				break;

			case 'ptg_extensions' :
				$sName = __( 'Included File Types', 'wp-simple-firewall' );
				$sSummary = __( 'The File Types (by File Extension) Included In The Scan', 'wp-simple-firewall' );
				$sDescription = __( 'Take a new line for each file extension.', 'wp-simple-firewall' )
								.'<br/>'.__( 'No commas(,) or periods(.) necessary.', 'wp-simple-firewall' )
								.'<br/>'.__( 'Remove all extensions to scan all file type (not recommended).', 'wp-simple-firewall' );
				break;

			case 'ptg_reinstall_links' :
				$sName = __( 'Show Re-Install Links', 'wp-simple-firewall' );
				$sSummary = __( 'Show Re-Install Links For Plugins', 'wp-simple-firewall' );
				$sDescription = __( "Show links to re-install plugins and offer re-install when activating plugins.", 'wp-simple-firewall' );
				break;

			case 'enabled_scan_apc' :
				$sName = __( 'Abandoned Plugin Scanner', 'wp-simple-firewall' );
				$sSummary = __( 'Enable The Abandoned Plugin Scanner', 'wp-simple-firewall' );
				$sDescription = __( "Scan your WordPress.org assets for whether they've been abandoned.", 'wp-simple-firewall' );
				break;

			case 'display_apc' :
				$sName = __( 'Highlight Plugins', 'wp-simple-firewall' );
				$sSummary = __( 'Highlight Abandoned Plugins', 'wp-simple-firewall' );
				$sDescription = __( "Abandoned plugins will be highlighted on the main plugins page.", 'wp-simple-firewall' );
				break;

			case 'mal_scan_enable' :
				$sName = __( 'Malware Scanner', 'wp-simple-firewall' );
				$sSummary = __( 'Enable Malware Scanner', 'wp-simple-firewall' );
				$sDescription = __( "Enabled detection of files infected with malware signatures.", 'wp-simple-firewall' );
				break;

			case 'mal_autorepair_core' :
				$sName = __( 'Auto-Repair WP Core', 'wp-simple-firewall' );
				$sSummary = __( 'Automatically Repair WordPress Core Files', 'wp-simple-firewall' );
				$sDescription = __( "Automatically reinstall any core files found to have potential malware.", 'wp-simple-firewall' );
				break;

			case 'mal_autorepair_plugins' :
				$sName = __( 'Auto-Repair WP Plugins', 'wp-simple-firewall' );
				$sSummary = __( 'Automatically Repair WordPress.org Plugins', 'wp-simple-firewall' );
				$sDescription = __( "Automatically repair any plugin files found to have potential malware.", 'wp-simple-firewall' )
								.'<br />'.sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ), __( 'Only applies to plugins installed from WordPress.org.', 'wp-simple-firewall' ) )
								.'<br />'.sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ), __( "Also deletes files if they're found to not be originally distributed with the plugin.", 'wp-simple-firewall' ) );
				break;

			case 'rt_file_wpconfig' :
				$sName = __( 'WP Config', 'wp-simple-firewall' );
				$sSummary = __( 'Realtime Protection For WP Config File', 'wp-simple-firewall' );
				$sDescription = __( "Realtime protection for the wp-config.php file.", 'wp-simple-firewall' );
				break;

			default:
				throw new \Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
		}

		$aOptionsParams[ 'name' ] = $sName;
		$aOptionsParams[ 'summary' ] = $sSummary;
		$aOptionsParams[ 'description' ] = $sDescription;
		return $aOptionsParams;
	}
}