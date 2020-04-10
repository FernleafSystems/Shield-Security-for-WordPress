<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_HackProtect extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @var HackGuard\Scan\Queue\Controller
	 */
	private $oScanQueueController;

	/**
	 * @var HackGuard\Scan\Controller\Base[]
	 */
	private $aScanCons;

	/**
	 * @var HackGuard\Lib\FileLocker\FileLockerController
	 */
	private $oFileLocker;

	/**
	 * @param array $aItems
	 * @return array
	 */
	public function addAdminMenuBarItems( array $aItems ) {
		$oCon = $this->getCon();
		$nCountFL = $this->getFileLocker()->countProblems();
		if ( $nCountFL > 0 ) {
			$aItems[] = [
				'id'       => $oCon->prefix( 'filelocker_problems' ),
				'title'    => __( 'File Locker', 'wp-simple-firewall' )
							  .sprintf( '<div class="wp-core-ui wp-ui-notification shield-counter"><span aria-hidden="true">%s</span></div>', $nCountFL ),
				'href'     => $this->getCon()->getModule_Insights()->getUrl_SubInsightsPage( 'scans' ),
				'warnings' => $nCountFL
			];
		}
		return $aItems;
	}

	protected function doPostConstruction() {
		$this->setCustomCronSchedules();
	}

	/**
	 * A action added to WordPress 'init' hook
	 */
	public function onWpInit() {
		parent::onWpInit();
		$this->getScanController();
	}

	/**
	 * @return HackGuard\Lib\FileLocker\FileLockerController
	 */
	public function getFileLocker() {
		if ( !isset( $this->oFileLocker ) ) {
			$this->oFileLocker = ( new HackGuard\Lib\FileLocker\FileLockerController() )
				->setMod( $this );
		}
		return $this->oFileLocker;
	}

	/**
	 * @return HackGuard\Scan\Queue\Controller
	 */
	public function getScanController() {
		if ( !isset( $this->oScanQueueController ) ) {
			$this->oScanQueueController = ( new HackGuard\Scan\Queue\Controller() )
				->setMod( $this );
		}
		return $this->oScanQueueController;
	}

	/**
	 * @param string $sSlug
	 * @return HackGuard\Scan\Controller\Base|mixed
	 */
	public function getScanCon( $sSlug ) {
		if ( !is_array( $this->aScanCons ) ) {
			$this->aScanCons = [];
		}
		if ( !isset( $this->aScanCons[ $sSlug ] ) ) {
			$sClass = '\FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\\'.ucwords( $sSlug );
			if ( @class_exists( $sClass ) ) {
				/** @var HackGuard\Scan\Controller\Base $oObj */
				$oObj = new $sClass();
				$this->aScanCons[ $sSlug ] = $oObj->setMod( $this );
			}
			else {
				$this->aScanCons[ $sSlug ] = false;
			}
		}
		return $this->aScanCons[ $sSlug ];
	}

	/**
	 * @inheritDoc
	 */
	protected function handleModAction( $sAction ) {
		switch ( $sAction ) {
			case  'scan_file_download':
				( new HackGuard\Lib\Utility\FileDownloadHandler() )
					->setDbHandler( $this->getDbHandler_ScanResults() )
					->downloadByItemId( (int)Services::Request()->query( 'rid', 0 ) );
				break;
			case  'filelocker_download_original':
			case  'filelocker_download_current':
				$this->getFileLocker()->handleFileDownloadRequest();
				break;
			default:
				break;
		}
	}

	protected function updateHandler() {
		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( $oOpts->getOpt( 'ptg_enable' ) == 'enabled' ) {
			$oOpts->setOpt( 'ptg_enable', 'Y' );
		}
		elseif ( $oOpts->getOpt( 'ptg_enable' ) == 'disabled' ) {
			$oOpts->setOpt( 'ptg_enable', 'N' );
		}

		$aRepairAreas = $oOpts->getRepairAreas();
		$aMap = [
			'attempt_auto_file_repair' => 'wp',
			'mal_autorepair_plugins'   => 'plugin',
		];
		foreach ( $aMap as $sOld => $sNew ) {
			$bWasEnabled = $oOpts->isOpt( $sOld, 'Y' );
			$nIsEnabled = array_search( $sNew, $aRepairAreas );
			if ( $bWasEnabled && ( $nIsEnabled === false ) ) {
				$aRepairAreas[] = $sNew;
			}
			elseif ( !$bWasEnabled && ( $nIsEnabled !== false ) ) {
				unset( $aRepairAreas[ $nIsEnabled ] );
			}
		}
		$this->setOpt( 'file_repair_areas', $aRepairAreas );

		{ // migrate old scan options
			if ( $oOpts->getOpt( 'enable_unrecognised_file_cleaner_scan' ) == 'enabled_delete_report' ) {
				$oOpts->setOpt( 'enable_unrecognised_file_cleaner_scan', 'enabled_delete_only' );
			}
			$sApcOpt = $oOpts->getOpt( 'enabled_scan_apc' );
			if ( strlen( $sApcOpt ) > 1 ) {
				$oOpts->setOpt( 'enabled_scan_apc', $sApcOpt == 'disabled' ? 'N' : 'Y' );
			}
			$sWpvOpt = $oOpts->getOpt( 'enable_wpvuln_scan' );
			if ( strlen( $sWpvOpt ) > 1 ) {
				$oOpts->setOpt( 'enable_wpvuln_scan', $sWpvOpt == 'disabled' ? 'N' : 'Y' );
			}
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

		$this->setOpt( 'ptg_candiskwrite_at', 0 );
		$this->resetRtBackupFiles();

		/** @var ICWP_WPSF_Processor_HackProtect $oPro */
		$oPro = $this->getProcessor();
		$oPro->getSubProScanner()->deleteCron(); // very important if the scan cron schedule is changed.
	}

	/**
	 * @return int[] - key is scan slug
	 */
	public function getLastScansAt() {
		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		/** @var Shield\Databases\Events\Select $oSel */
		$oSel = $this->getCon()
					 ->getModule_Events()
					 ->getDbHandler_Events()
					 ->getQuerySelector();
		$aEvents = $oSel->getLatestForAllEvents();

		$aLatest = [];
		foreach ( $oOpts->getScanSlugs() as $sScan ) {
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
					 ->getDbHandler_Events()
					 ->getQuerySelector();
		$oEntry = $oSel->getLatestForEvent( $sScan.'_scan_run' );
		return ( $oEntry instanceof Shield\Databases\Events\EntryVO ) ? $oEntry->created_at : 0;
	}

	/**
	 * @return int
	 */
	public function getScanNotificationInterval() {
		return DAY_IN_SECONDS*(int)max( 0, apply_filters( 'icwp_shield_scan_notification_interval', 7 ) );
	}

	/**
	 * @return bool
	 * @deprecated 8.8.0
	 */
	public function isIncludeFileLists() {
		return false;
	}

	/**
	 * @return $this
	 */
	protected function setCustomCronSchedules() {
		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		$nFreq = $oOpts->getScanFrequency();
		Services::WpCron()
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
	 * @return $this
	 */
	protected function cleanFileExclusions() {
		$aExclusions = [];

		foreach ( $this->getUfcFileExclusions() as $nKey => $sExclusion ) {
			$sExclusion = wp_normalize_path( trim( $sExclusion ) );

			if ( preg_match( '/^#(.+)#$/', $sExclusion, $aMatches ) ) { // it's regex
				// ignore it
			}
			elseif ( strpos( $sExclusion, '/' ) === false ) { // filename only
				$sExclusion = trim( preg_replace( '#[^.0-9a-z_-]#i', '', $sExclusion ) );
			}

			if ( !empty( $sExclusion ) ) {
				$aExclusions[] = $sExclusion;
			}
		}

		return $this->setOpt( 'ufc_exclusions', array_unique( $aExclusions ) );
	}

	/**
	 * @return bool
	 */
	public function isWpvulnPluginsHighlightEnabled() {
		$oWpvCon = $this->getScanCon( 'wpv' );
		if ( $oWpvCon->isEnabled() ) {
			$sOpt = apply_filters( 'icwp_shield_wpvuln_scan_display', 'securityadmin' );
		}
		else {
			$sOpt = 'disabled';
		}
		return ( $sOpt != 'disabled' ) && Services::WpUsers()->isUserAdmin()
			   && ( ( $sOpt != 'securityadmin' ) || $this->getCon()->isPluginAdmin() );
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
	 * @return bool
	 */
	public function isPtgEnabled() {
		return $this->isModuleEnabled() && $this->isPremium()
			   && $this->isOpt( 'ptg_enable', 'enabled' )
			   && $this->getOptions()->isOptReqsMet( 'ptg_enable' )
			   && $this->canPtgWriteToDisk();
	}

	/**
	 * @param string $sSlug
	 * @return bool
	 */
	protected function isScanEnabled( $sSlug ) {
		return $this->getScanCon( $sSlug )
					->isEnabled();
	}

	public function insertCustomJsVars_Admin() {
		parent::insertCustomJsVars_Admin();

		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( Services::WpPost()->isCurrentPage( 'plugins.php' )
			 && $oOpts->isPtgReinstallLinks() && $this->getScanCon( 'ptg' )->isReady() ) {
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
	 * @param string $sSection
	 * @return array
	 */
	protected function getSectionWarnings( $sSection ) {
		$aWarnings = [];

		switch ( $sSection ) {

			case 'section_scan_ptg': //TODO
				if ( !$this->canPtgWriteToDisk() ) {
					$aWarnings[] = sprintf( __( 'Sorry, this feature is not available because we cannot write to disk at this location: "%s"', 'wp-simple-firewall' ), $this->getPtgSnapsBaseDir() );
				}
				break;

			case 'section_realtime':
				$bCanHandshake = $this->getCon()
									  ->getModule_Plugin()
									  ->getShieldNetApiController()
									  ->canHandshake();
				if ( !$bCanHandshake ) {
					$aWarnings[] = sprintf( __( 'Not available as your site cannot handshake with ShieldNET API.', 'wp-simple-firewall' ), 'OpenSSL' );
				}
//				if ( !Services::Encrypt()->isSupportedOpenSslDataEncryption() ) {
//					$aWarnings[] = sprintf( __( 'Not available because the %s extension is not available.', 'wp-simple-firewall' ), 'OpenSSL' );
//				}
//				if ( !Services::WpFs()->isFilesystemAccessDirect() ) {
//					$aWarnings[] = sprintf( __( "Not available because PHP/WordPress doesn't have direct filesystem access.", 'wp-simple-firewall' ), 'OpenSSL' );
//				}
//				else {
//					$sPath = $this->getRtMapFileKeyToFilePath( 'wpconfig' );
//					if ( !$this->getRtCanWriteFile( $sPath ) ) {
//						$aWarnings[] = sprintf( __( "The %s file isn't writable and so can't be further protected.", 'wp-simple-firewall' ), 'wp-config.php' );
//					}
//				}
				break;
		}

		return $aWarnings;
	}

	/**
	 * @return string|false
	 */
	public function getPtgSnapsBaseDir() {
		return $this->getCon()->getPluginCachePath( 'ptguard/' );
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
			$bCanWrite = ( new Shield\Modules\HackGuard\Lib\FileLocker\Ops\TestWritable() )->run( $sFile );
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
	 * @return string
	 */
	public function getTempDir() {
		$sDir = $this->getCon()->getPluginCachePath( 'scans' );
		return Services::WpFs()->mkdir( $sDir ) ? $sDir : false;
	}

	/**
	 * @param array $aAllNotices
	 * @return array
	 */
	public function addInsightsNoticeData( $aAllNotices ) {
		/** @var HackGuard\Strings $oStrings */
		$oStrings = $this->getStrings();
		$aScanNames = $oStrings->getScanNames();

		$aNotices = [
			'title'    => __( 'Scans', 'wp-simple-firewall' ),
			'messages' => []
		];

		$sScansUrl = $this->getCon()->getModule_Insights()->getUrl_SubInsightsPage( 'scans' );

		{// Core files
			if ( !$this->isScanEnabled( 'wcf' ) ) {
				$aNotices[ 'messages' ][ 'wcf' ] = [
					'title'   => $aScanNames[ 'wcf' ],
					'message' => __( 'Core File scanner is not enabled.', 'wp-simple-firewall' ),
					'href'    => $this->getUrl_DirectLinkToOption( 'enable_core_file_integrity_scan' ),
					'action'  => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Options', 'wp-simple-firewall' ) ),
					'rec'     => __( 'Automatic WordPress Core File scanner should be turned-on.', 'wp-simple-firewall' )
				];
			}
			elseif ( $this->getScanCon( 'wcf' )->getScanHasProblem() ) {
				$aNotices[ 'messages' ][ 'wcf' ] = [
					'title'   => $aScanNames[ 'wcf' ],
					'message' => __( 'Modified WordPress core files found.', 'wp-simple-firewall' ),
					'href'    => $sScansUrl,
					'action'  => __( 'Run Scan', 'wp-simple-firewall' ),
					'rec'     => __( 'Scan WP core files and repair any files that are flagged as modified.', 'wp-simple-firewall' )
				];
			}
		}

		{// Unrecognised
			if ( !$this->isScanEnabled( 'ufc' ) ) {
				$aNotices[ 'messages' ][ 'ufc' ] = [
					'title'   => $aScanNames[ 'ufc' ],
					'message' => __( 'Unrecognised File scanner is not enabled.', 'wp-simple-firewall' ),
					'href'    => $this->getUrl_DirectLinkToSection( 'section_scan_ufc' ),
					'action'  => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Options', 'wp-simple-firewall' ) ),
					'rec'     => __( 'Automatic scanning for non-WordPress core files is recommended.', 'wp-simple-firewall' )
				];
			}
			elseif ( $this->getScanCon( 'ufc' )->getScanHasProblem() ) {
				$aNotices[ 'messages' ][ 'ufc' ] = [
					'title'   => $aScanNames[ 'ufc' ],
					'message' => __( 'Unrecognised files found in WordPress Core directory.', 'wp-simple-firewall' ),
					'href'    => $sScansUrl,
					'action'  => __( 'Run Scan', 'wp-simple-firewall' ),
					'rec'     => __( 'Scan and remove any files that are not meant to be in the WP core directories.', 'wp-simple-firewall' )
				];
			}
		}

		{// Plugin/Theme Guard
			$oPTG = $this->getScanCon( 'ptg' );
			if ( !$oPTG->isEnabled() ) {
				$aNotices[ 'messages' ][ 'ptg' ] = [
					'title'   => $aScanNames[ 'ptg' ],
					'message' => __( 'Automatic Plugin/Themes Guard is not enabled.', 'wp-simple-firewall' ),
					'href'    => $this->getUrl_DirectLinkToOption( 'ptg_enable' ),
					'action'  => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Options', 'wp-simple-firewall' ) ),
					'rec'     => __( 'Automatic detection of plugin/theme modifications is recommended.', 'wp-simple-firewall' )
				];
			}
			elseif ( $oPTG->getScanHasProblem() ) {
				$aNotices[ 'messages' ][ 'ptg' ] = [
					'title'   => $aScanNames[ 'ptg' ],
					'message' => __( 'A plugin/theme was found to have been modified.', 'wp-simple-firewall' ),
					'href'    => $sScansUrl,
					'action'  => __( 'Run Scan', 'wp-simple-firewall' ),
					'rec'     => __( 'Reviewing modifications to your plugins/themes is recommended.', 'wp-simple-firewall' )
				];
			}
		}

		{// Vulnerability Scanner
			if ( !$this->isScanEnabled( 'wpv' ) ) {
				$aNotices[ 'messages' ][ 'wpv' ] = [
					'title'   => $aScanNames[ 'wpv' ],
					'message' => __( 'Vulnerability Scanner is not enabled.', 'wp-simple-firewall' ),
					'href'    => $this->getUrl_DirectLinkToSection( 'section_scan_wpv' ),
					'action'  => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Options', 'wp-simple-firewall' ) ),
					'rec'     => __( 'Automatic detection of vulnerabilities is recommended.', 'wp-simple-firewall' )
				];
			}
			elseif ( $this->getScanCon( 'wpv' )->getScanHasProblem() ) {
				$aNotices[ 'messages' ][ 'wpv' ] = [
					'title'   => $aScanNames[ 'wpv' ],
					'message' => __( 'At least 1 item has known vulnerabilities.', 'wp-simple-firewall' ),
					'href'    => $sScansUrl,
					'action'  => __( 'Run Scan', 'wp-simple-firewall' ),
					'rec'     => __( 'Items with known vulnerabilities should be updated, removed, or replaced.', 'wp-simple-firewall' )
				];
			}
		}

		{// Abandoned Plugins
			if ( !$this->isScanEnabled( 'apc' ) ) {
				$aNotices[ 'messages' ][ 'apc' ] = [
					'title'   => $aScanNames[ 'apc' ],
					'message' => __( 'Abandoned Plugins Scanner is not enabled.', 'wp-simple-firewall' ),
					'href'    => $this->getUrl_DirectLinkToSection( 'section_scan_apc' ),
					'action'  => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Options', 'wp-simple-firewall' ) ),
					'rec'     => __( 'Automatic detection of abandoned plugins is recommended.', 'wp-simple-firewall' )
				];
			}
			elseif ( $this->getScanCon( 'apc' )->getScanHasProblem() ) {
				$aNotices[ 'messages' ][ 'apc' ] = [
					'title'   => $aScanNames[ 'apc' ],
					'message' => __( 'At least 1 plugin on your site is abandoned.', 'wp-simple-firewall' ),
					'href'    => $sScansUrl,
					'action'  => __( 'Run Scan', 'wp-simple-firewall' ),
					'rec'     => __( 'Plugins that have been abandoned represent a potential risk to your site.', 'wp-simple-firewall' )
				];
			}
		}

		{// Malware
			if ( !$this->isScanEnabled( 'mal' ) ) {
				$aNotices[ 'messages' ][ 'mal' ] = [
					'title'   => $aScanNames[ 'mal' ],
					'message' => sprintf( __( '%s Scanner is not enabled.' ), $aScanNames[ 'mal' ] ),
					'href'    => $this->getUrl_DirectLinkToSection( 'section_scan_mal' ),
					'action'  => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Options', 'wp-simple-firewall' ) ),
					'rec'     => __( 'Automatic detection of Malware is recommended.', 'wp-simple-firewall' )
				];
			}
			elseif ( $this->getScanCon( 'mal' )->getScanHasProblem() ) {
				$aNotices[ 'messages' ][ 'mal' ] = [
					'title'   => $aScanNames[ 'mal' ],
					'message' => __( 'At least 1 file with potential Malware has been discovered.', 'wp-simple-firewall' ),
					'href'    => $sScansUrl,
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
		/** @var HackGuard\Strings $oStrings */
		$oStrings = $this->getStrings();
		/** @var HackGuard\Options $oOpts */
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

			$bCore = $this->isScanEnabled( 'wcf' );
			$aThis[ 'key_opts' ][ 'wcf' ] = [
				'name'    => __( 'WP Core File Scan', 'wp-simple-firewall' ),
				'enabled' => $bCore,
				'summary' => $bCore ?
					__( 'Core files scanned regularly for hacks', 'wp-simple-firewall' )
					: __( "Core files are never scanned for hacks!", 'wp-simple-firewall' ),
				'weight'  => 2,
				'href'    => $this->getUrl_DirectLinkToOption( 'enable_core_file_integrity_scan' ),
			];
			if ( $bCore && !$oOpts->isRepairFileWP() ) {
				$aThis[ 'key_opts' ][ 'wcf_repair' ] = [
					'name'    => __( 'WP Core File Repair', 'wp-simple-firewall' ),
					'enabled' => $oOpts->isRepairFileWP(),
					'summary' => $oOpts->isRepairFileWP() ?
						__( 'Core files are automatically repaired', 'wp-simple-firewall' )
						: __( "Core files aren't automatically repaired!", 'wp-simple-firewall' ),
					'weight'  => 1,
					'href'    => $this->getUrl_DirectLinkToOption( 'file_repair_areas' ),
				];
			}

			$bUcf = $this->isScanEnabled( 'ufc' );
			$aThis[ 'key_opts' ][ 'ufc' ] = [
				'name'    => __( 'Unrecognised Files', 'wp-simple-firewall' ),
				'enabled' => $bUcf,
				'summary' => $bUcf ?
					__( 'Core directories scanned regularly for unrecognised files', 'wp-simple-firewall' )
					: __( "WP Core is never scanned for unrecognised files!", 'wp-simple-firewall' ),
				'weight'  => 2,
				'href'    => $this->getUrl_DirectLinkToSection( 'section_scan_ufc' ),
			];
			if ( $bUcf && !$oOpts->isUfsDeleteFiles() ) {
				$aThis[ 'key_opts' ][ 'ufc_repair' ] = [
					'name'    => __( 'Unrecognised Files Removal', 'wp-simple-firewall' ),
					'enabled' => $oOpts->isUfsDeleteFiles(),
					'summary' => $oOpts->isUfsDeleteFiles() ?
						__( 'Unrecognised files are automatically removed', 'wp-simple-firewall' )
						: __( "Unrecognised files aren't automatically removed!", 'wp-simple-firewall' ),
					'weight'  => 1,
					'href'    => $this->getUrl_DirectLinkToSection( 'section_scan_ufc' ),
				];
			}

			$bWpv = $this->isScanEnabled( 'wpv' );
			$aThis[ 'key_opts' ][ 'wpv' ] = [
				'name'    => __( 'Vulnerability Scan', 'wp-simple-firewall' ),
				'enabled' => $bWpv,
				'summary' => $bWpv ?
					__( 'Regularly scanning for known vulnerabilities', 'wp-simple-firewall' )
					: __( "Plugins/Themes never scanned for vulnerabilities!", 'wp-simple-firewall' ),
				'weight'  => 2,
				'href'    => $this->getUrl_DirectLinkToSection( 'section_scan_wpv' ),
			];
			$bWpvAutoUpdates = $oOpts->isWpvulnAutoupdatesEnabled();
			if ( $bWpv && !$bWpvAutoUpdates ) {
				$aThis[ 'key_opts' ][ 'wpv_repair' ] = [
					'name'    => __( 'Auto Update', 'wp-simple-firewall' ),
					'enabled' => $bWpvAutoUpdates,
					'summary' => $bWpvAutoUpdates ?
						__( 'Vulnerable items are automatically updated', 'wp-simple-firewall' )
						: __( "Vulnerable items aren't automatically updated!", 'wp-simple-firewall' ),
					'weight'  => 1,
					'href'    => $this->getUrl_DirectLinkToSection( 'section_scan_wpv' ),
				];
			}

			$bPtg = $this->isScanEnabled( 'ptg' );
			$aThis[ 'key_opts' ][ 'ptg' ] = [
				'title'   => $aScanNames[ 'ptg' ],
				'name'    => __( 'Plugin/Theme Guard', 'wp-simple-firewall' ),
				'enabled' => $bPtg,
				'summary' => $bPtg ?
					__( 'Plugins and Themes are guarded against tampering', 'wp-simple-firewall' )
					: __( "Plugins and Themes are never scanned for tampering!", 'wp-simple-firewall' ),
				'weight'  => 2,
				'href'    => $this->getUrl_DirectLinkToOption( 'ptg_enable' ),
			];

			$bMal = $this->isScanEnabled( 'mal' );
			$aThis[ 'key_opts' ][ 'mal' ] = [
				'title'   => $aScanNames[ 'mal' ],
				'name'    => $aScanNames[ 'mal' ],
				'enabled' => $bMal,
				'summary' => $bMal ?
					sprintf( __( '%s Scanner is enabled.' ), $aScanNames[ 'mal' ] )
					: sprintf( __( '%s Scanner is not enabled.' ), $aScanNames[ 'mal' ] ),
				'weight'  => 2,
				'href'    => $this->getUrl_DirectLinkToSection( 'section_scan_mal' ),
			];

			$bApc = $this->isScanEnabled( 'apc' );
			$aThis[ 'key_opts' ][ 'apc' ] = [
				'title'   => $aScanNames[ 'apc' ],
				'name'    => $aScanNames[ 'apc' ],
				'enabled' => $bApc,
				'summary' => $bApc ?
					sprintf( __( '%s Scanner is enabled.' ), $aScanNames[ 'apc' ] )
					: sprintf( __( '%s Scanner is not enabled.' ), $aScanNames[ 'apc' ] ),
				'weight'  => 2,
				'href'    => $this->getUrl_DirectLinkToSection( 'section_scan_apc' ),
			];
		}

		$aAllData[ $this->getSlug() ] = $aThis;
		return $aAllData;
	}

	/**
	 * @return Shield\Databases\FileLocker\Handler
	 */
	public function getDbHandler_FileLocker() {
		return $this->getDbH( 'file_protect' );
	}

	/**
	 * @return false|Shield\Databases\ScanQueue\Handler
	 */
	public function getDbHandler_ScanQueue() {
		return $this->getDbH( 'scanq' );
	}

	/**
	 * @return false|Shield\Databases\Scanner\Handler
	 */
	public function getDbHandler_ScanResults() {
		return $this->getDbH( 'scanresults' );
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function isReadyToExecute() {
		return ( $this->getDbHandler_ScanQueue() instanceof Shield\Databases\ScanQueue\Handler )
			   && $this->getDbHandler_ScanQueue()->isReady()
			   && ( $this->getDbHandler_ScanResults() instanceof Shield\Databases\Scanner\Handler )
			   && $this->getDbHandler_ScanQueue()->isReady()
			   && parent::isReadyToExecute();
	}

	public function onPluginDeactivate() {
		// 1. Clean out the scanners
		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		foreach ( $oOpts->getScanSlugs() as $sSlug ) {
			$this->getScanCon( $sSlug )->purge();
		}
		// 2. Clean out the file locker
		$this->getFileLocker()->purge();
	}

	/**
	 * @return string
	 */
	protected function getNamespaceBase() {
		return 'HackGuard';
	}

	/**
	 * @return string
	 * @deprecated 8.8.0
	 */
	public function getWpvulnPluginsHighlightOption() {
		return 'disabled';
	}
}