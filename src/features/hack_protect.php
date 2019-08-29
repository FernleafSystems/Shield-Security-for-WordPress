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
		parent::updateHandler();
		$this->setPtgUpdateStoreFormat( true );
//			 ->setPtgRebuildSelfRequired( true ) // this is permanently required until a better solution is found
		Services::WpFs()->deleteDir( $this->getScansTempDir() );
	}

	/**
	 * @return string
	 */
	public function getScansTempDir() {
		$sDir = $this->getCon()->getPluginCachePath( 'scans' );
		return Services::WpFs()->mkdir( $sDir ) ? $sDir : false;
	}

	/**
	 */
	public function handleModRequest() {
		$oReq = Services::Request();
		switch ( $oReq->query( 'exec' ) && $this->getCon()->isPluginAdmin() ) {
			case  'scan_file_download':
				/** @var \ICWP_WPSF_Processor_HackProtect $oPro */
				$oPro = $this->getProcessor();
				$oPro->getSubProScanner()->downloadItemFile( $oReq->query( 'rid' ) );
				break;
			default:
				break;
		}
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
	 */
	protected function doExtraSubmitProcessing() {
		$this->clearIcSnapshots();
		$this->cleanFileExclusions();
		$this->cleanPtgFileExtensions();

		$oOpts = $this->getOptions();
		if ( $oOpts->isOptChanged( 'ptg_enable' ) || $oOpts->isOptChanged( 'ptg_depth' ) || $oOpts->isOptChanged( 'ptg_extensions' ) ) {
			$this->setPtgLastBuildAt( 0 );
			/** @var ICWP_WPSF_Processor_HackProtect $oPro */
			$oPro = $this->getProcessor();
			$oPro->getSubProScanner()
				 ->getSubProcessorPtg()
				 ->resetScan();
		}

		$this->setOpt( 'ptg_candiskwrite_at', 0 );
		$this->resetRtBackupFiles();
	}

	/**
	 * @return string[]
	 */
	public function getAllScanSlugs() {
		return $this->getDef( 'all_scan_slugs' );
	}

	/**
	 * @return int[] - key is scan slug
	 */
	public function getLastScansAt() {
		/** @var Shield\Databases\Events\Select $oSel */
		$oSel = $this->getCon()
					 ->getModule_Events()
					 ->getDbHandler()
					 ->getQuerySelector();
		$aEvents = $oSel->getLatestForAllEvents();

		$aLatest = [];
		foreach ( $this->getAllScanSlugs() as $sScan ) {
			$sEvt = $sScan.'_scan_run';
			$aLatest[ $sScan ] = isset( $aEvents[ $sEvt ] ) ? $aEvents[ $sEvt ]->created_at : 0;
		}
		return $aLatest;
	}

	/**
	 * @param string $sScan ptg, wcf, ufc, wpv
	 * @return int
	 */
	public function getLastScanAt( $sScan ) {
		/** @var Shield\Databases\Events\Select $oSel */
		$oSel = $this->getCon()
					 ->getModule_Events()
					 ->getDbHandler()
					 ->getQuerySelector();
		$oEntry = $oSel->getLatestForEvent( $sScan.'_scan_run' );
		return ( $oEntry instanceof Shield\Databases\Events\EntryVO ) ? $oEntry->created_at : 0;
	}

	/**
	 * @param string $sScan ptg, wcf, ufc, wpv
	 * @return bool
	 */
	public function getScanHasProblem( $sScan ) {
		/** @var Shield\Databases\Scanner\Select $oSel */
		$oSel = $this->getDbHandler()->getQuerySelector();
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
	 * @return $this
	 */
	protected function setCustomCronSchedules() {
		/** @var Shield\Modules\HackGuard\Options $oStrings */
		$oOpts = $this->getOptions();
		$nFreq = $oOpts->getScanFrequency();
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
				$sExclusion = trim( preg_replace( '#[^.0-9a-z_-]#i', '', $sExclusion ) );
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

		$bCanWrite = $this->getOpt( 'ptg_candiskwrite' ) && !$bLastCheckExpired;
		if ( !$bCanWrite ) {
			$oFS = Services::WpFs();
			$sDir = $this->getPtgSnapsBaseDir();

			if ( $sDir && $oFS->mkdir( $sDir ) ) {
				$sTestFile = path_join( $sDir, 'test.txt' );
				$oFS->putFileContent( $sTestFile, 'test-'.$nNow );
				$sContents = $oFS->exists( $sTestFile ) ? $oFS->getFileContent( $sTestFile ) : '';
				if ( $sContents === 'test-'.$nNow ) {
					$oFS->deleteFile( $sTestFile );
					$bCanWrite = !$oFS->exists( $sTestFile );
					$this->setOpt( 'ptg_candiskwrite', $bCanWrite );
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
		return $this->getCon()->getPluginCachePath( 'ptguard/' );
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
			   && $this->getOptions()->isOptReqsMet( 'ptg_enable' )
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
	public function isMalScanAutoRepair() {
		return $this->isMalAutoRepairCore() || $this->isMalAutoRepairPlugins() || $this->isMalAutoRepairSurgical();
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

	/**
	 * @return bool
	 */
	public function isMalAutoRepairSurgical() {
		return $this->isOpt( 'mal_autorepair_surgical', 'Y' );
	}

	public function insertCustomJsVars_Admin() {
		parent::insertCustomJsVars_Admin();

		if ( Services::WpPost()->isCurrentPage( 'plugins.php' ) && $this->isPtgReinstallLinks() ) {
			wp_localize_script(
				$this->prefix( 'global-plugin' ),
				'icwp_wpsf_vars_hp',
				[
					'ajax_plugin_reinstall' => $this->getAjaxActionData( 'plugin_reinstall' ),
					'reinstallable'         => Services::WpPlugins()->getInstalledWpOrgPluginFiles(),
					'strings'               => [
						'reinstall_first' => __( 'Re-install First', 'wp-simple-firewall' )
											 .'. '.__( 'Then Activate', 'wp-simple-firewall' ),
						'okay_reinstall'  => sprintf( '%s, %s',
							__( 'Yes', 'wp-simple-firewall' ), __( 'Re-Install It', 'wp-simple-firewall' ) ),
						'activate_only'   => __( 'Activate Only', 'wp-simple-firewall' ),
						'cancel'          => __( 'Cancel', 'wp-simple-firewall' ),
					]
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

			case 'section_scan_wcf':
				$nTime = $this->getLastScanAt( 'wcf' );
				break;

			case 'section_scan_ufc':
				$nTime = $this->getLastScanAt( 'ufc' );
				break;

			case 'section_scan_ptg':
				$nTime = $this->getLastScanAt( 'ptg' );
				break;

			case 'section_scan_wpv':
				$nTime = $this->getLastScanAt( 'wpv' );
				break;

			case 'section_scan_mal':
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
	 * @param string $sSection
	 * @return array
	 */
	protected function getSectionWarnings( $sSection ) {
		$aWarnings = [];

		switch ( $sSection ) {

			case 'section_scan_ptg':
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
		$oOpts = $this->getOptions();
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
		/** @var Shield\Modules\HackGuard\Strings $oStrings */
		$oStrings = $this->getStrings();
		$aScanNames = $oStrings->getScanNames();

		$aNotices = [
			'title'    => __( 'Scans', 'wp-simple-firewall' ),
			'messages' => []
		];

		{// Core files
			if ( !$this->isWcfScanEnabled() ) {
				$aNotices[ 'messages' ][ 'wcf' ] = [
					'title'   => $aScanNames[ 'wcf' ],
					'message' => __( 'Core File scanner is not enabled.', 'wp-simple-firewall' ),
					'href'    => $this->getUrl_DirectLinkToSection( 'section_scan_wcf' ),
					'action'  => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Options', 'wp-simple-firewall' ) ),
					'rec'     => __( 'Automatic WordPress Core File scanner should be turned-on.', 'wp-simple-firewall' )
				];
			}
			else if ( $this->getScanHasProblem( 'wcf' ) ) {
				$aNotices[ 'messages' ][ 'wcf' ] = [
					'title'   => $aScanNames[ 'wcf' ],
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
					'title'   => $aScanNames[ 'ufc' ],
					'message' => __( 'Unrecognised File scanner is not enabled.', 'wp-simple-firewall' ),
					'href'    => $this->getUrl_DirectLinkToSection( 'section_scan_ufc' ),
					'action'  => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Options', 'wp-simple-firewall' ) ),
					'rec'     => __( 'Automatic scanning for non-WordPress core files is recommended.', 'wp-simple-firewall' )
				];
			}
			else if ( $this->getScanHasProblem( 'ufc' ) ) {
				$aNotices[ 'messages' ][ 'ufc' ] = [
					'title'   => $aScanNames[ 'ufc' ],
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
					'title'   => $aScanNames[ 'ptg' ],
					'message' => __( 'Automatic Plugin/Themes Guard is not enabled.', 'wp-simple-firewall' ),
					'href'    => $this->getUrl_DirectLinkToSection( 'section_scan_ptg' ),
					'action'  => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Options', 'wp-simple-firewall' ) ),
					'rec'     => __( 'Automatic detection of plugin/theme modifications is recommended.', 'wp-simple-firewall' )
				];
			}
			else if ( $this->getScanHasProblem( 'ptg' ) ) {
				$aNotices[ 'messages' ][ 'ptg' ] = [
					'title'   => $aScanNames[ 'ptg' ],
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
					'title'   => $aScanNames[ 'wpv' ],
					'message' => __( 'Vulnerability Scanner is not enabled.', 'wp-simple-firewall' ),
					'href'    => $this->getUrl_DirectLinkToSection( 'section_scan_wpv' ),
					'action'  => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Options', 'wp-simple-firewall' ) ),
					'rec'     => __( 'Automatic detection of vulnerabilities is recommended.', 'wp-simple-firewall' )
				];
			}
			else if ( $this->getScanHasProblem( 'wpv' ) ) {
				$aNotices[ 'messages' ][ 'wpv' ] = [
					'title'   => $aScanNames[ 'wpv' ],
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
					'title'   => $aScanNames[ 'apc' ],
					'message' => __( 'Abandoned Plugins Scanner is not enabled.', 'wp-simple-firewall' ),
					'href'    => $this->getUrl_DirectLinkToSection( 'section_scan_apc' ),
					'action'  => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Options', 'wp-simple-firewall' ) ),
					'rec'     => __( 'Automatic detection of abandoned plugins is recommended.', 'wp-simple-firewall' )
				];
			}
			else if ( $this->getScanHasProblem( 'apc' ) ) {
				$aNotices[ 'messages' ][ 'apc' ] = [
					'title'   => $aScanNames[ 'apc' ],
					'message' => __( 'At least 1 plugin on your site is abandoned.', 'wp-simple-firewall' ),
					'href'    => $this->getUrlManualScan(),
					'action'  => __( 'Run Scan', 'wp-simple-firewall' ),
					'rec'     => __( 'Plugins that have been abandoned represent a potential risk to your site.', 'wp-simple-firewall' )
				];
			}
		}

		{// Malware
			if ( !$this->isMalScanEnabled() ) {
				$aNotices[ 'messages' ][ 'mal' ] = [
					'title'   => $aScanNames[ 'mal' ],
					'message' => sprintf( __( '%s Scanner is not enabled.' ), $aScanNames[ 'mal' ] ),
					'href'    => $this->getUrl_DirectLinkToSection( 'section_scan_mal' ),
					'action'  => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Options', 'wp-simple-firewall' ) ),
					'rec'     => __( 'Automatic detection of Malware is recommended.', 'wp-simple-firewall' )
				];
			}
			else if ( $this->getScanHasProblem( 'mal' ) ) {
				$aNotices[ 'messages' ][ 'mal' ] = [
					'title'   => $aScanNames[ 'mal' ],
					'message' => __( 'At least 1 file with potential Malware has been discovered.', 'wp-simple-firewall' ),
					'href'    => $this->getUrlManualScan(),
					'action'  => __( 'Run Scan', 'wp-simple-firewall' ),
					'rec'     => __( 'Files identified as potential malware should be examined as soon as possible.', 'wp-simple-firewall' )
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
		/** @var Shield\Modules\HackGuard\Strings $oStrings */
		$oStrings = $this->getStrings();
		/** @var Shield\Modules\HackGuard\Options $oStrings */
		$oOpts = $this->getOptions();
		$aScanNames = $oStrings->getScanNames();

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
			$bGoodFrequency = $oOpts->getScanFrequency() > 1;
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
				'href'    => $this->getUrl_DirectLinkToSection( 'section_scan_wcf' ),
			];
			if ( $bCore && !$this->isWcfScanAutoRepair() ) {
				$aThis[ 'key_opts' ][ 'wcf_repair' ] = [
					'name'    => __( 'WP Core File Repair', 'wp-simple-firewall' ),
					'enabled' => $this->isWcfScanAutoRepair(),
					'summary' => $this->isWcfScanAutoRepair() ?
						__( 'Core files are automatically repaired', 'wp-simple-firewall' )
						: __( "Core files aren't automatically repaired!", 'wp-simple-firewall' ),
					'weight'  => 1,
					'href'    => $this->getUrl_DirectLinkToSection( 'section_scan_wcf' ),
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
				'href'    => $this->getUrl_DirectLinkToSection( 'section_scan_ufc' ),
			];
			if ( $bUcf && !$this->isUfcDeleteFiles() ) {
				$aThis[ 'key_opts' ][ 'ufc_repair' ] = [
					'name'    => __( 'Unrecognised Files Removal', 'wp-simple-firewall' ),
					'enabled' => $this->isUfcDeleteFiles(),
					'summary' => $this->isUfcDeleteFiles() ?
						__( 'Unrecognised files are automatically removed', 'wp-simple-firewall' )
						: __( "Unrecognised files aren't automatically removed!", 'wp-simple-firewall' ),
					'weight'  => 1,
					'href'    => $this->getUrl_DirectLinkToSection( 'section_scan_ufc' ),
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
				'href'    => $this->getUrl_DirectLinkToSection( 'section_scan_wpv' ),
			];
			if ( $bWpv && !$this->isWpvulnAutoupdatesEnabled() ) {
				$aThis[ 'key_opts' ][ 'wpv_repair' ] = [
					'name'    => __( 'Auto Update', 'wp-simple-firewall' ),
					'enabled' => $this->isWpvulnAutoupdatesEnabled(),
					'summary' => $this->isWpvulnAutoupdatesEnabled() ?
						__( 'Vulnerable items are automatically updated', 'wp-simple-firewall' )
						: __( "Vulnerable items aren't automatically updated!", 'wp-simple-firewall' ),
					'weight'  => 1,
					'href'    => $this->getUrl_DirectLinkToSection( 'section_scan_wpv' ),
				];
			}

			$bPtg = $this->isPtgEnabled();
			$aThis[ 'key_opts' ][ 'ptg' ] = [
				'title'   => $aScanNames[ 'ptg' ],
				'name'    => __( 'Plugin/Theme Guard', 'wp-simple-firewall' ),
				'enabled' => $bPtg,
				'summary' => $bPtg ?
					__( 'Plugins and Themes are guarded against tampering', 'wp-simple-firewall' )
					: __( "Plugins and Themes are never scanned for tampering!", 'wp-simple-firewall' ),
				'weight'  => 2,
				'href'    => $this->getUrl_DirectLinkToSection( 'section_scan_ptg' ),
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
			$this->getCon()->getModule_Insights()->getUrl_AdminPage()
		);
	}

	/**
	 * @return Shield\Databases\Scanner\Handler
	 */
	protected function loadDbHandler() {
		return new Shield\Databases\Scanner\Handler();
	}

	/**
	 * @return string
	 */
	protected function getNamespaceBase() {
		return 'HackGuard';
	}

	/**
	 * @return int
	 * @deprecated 8.1
	 */
	public function getScanFrequency() {
		return (int)$this->getOpt( 'scan_frequency', 1 );
	}
}