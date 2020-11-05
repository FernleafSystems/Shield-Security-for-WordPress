<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @deprecated 10.1
 */
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

	protected function doPostConstruction() {
		$this->setCustomCronSchedules();
	}

	public function onWpInit() {
		parent::onWpInit();
		$this->getScanQueueController();
	}

	public function getFileLocker() :HackGuard\Lib\FileLocker\FileLockerController {
		if ( !isset( $this->oFileLocker ) ) {
			$this->oFileLocker = ( new HackGuard\Lib\FileLocker\FileLockerController() )
				->setMod( $this );
		}
		return $this->oFileLocker;
	}

	public function getScanQueueController() :HackGuard\Scan\Queue\Controller {
		if ( !isset( $this->oScanQueueController ) ) {
			$this->oScanQueueController = ( new HackGuard\Scan\Queue\Controller() )
				->setMod( $this );
		}
		return $this->oScanQueueController;
	}

	/**
	 * @return HackGuard\Scan\Controller\Base[]
	 */
	public function getAllScanCons() :array {
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();
		foreach ( $opts->getScanSlugs() as $scanSlug ) {
			$this->getScanCon( $scanSlug );
		}
		return $this->aScanCons;
	}

	/**
	 * @param string $slug
	 * @return HackGuard\Scan\Controller\Base|mixed
	 */
	public function getScanCon( string $slug ) {
		if ( !is_array( $this->aScanCons ) ) {
			$this->aScanCons = [];
		}
		if ( !isset( $this->aScanCons[ $slug ] ) ) {
			$class = $this->getNamespace().'Scan\Controller\\'.ucwords( $slug );
			if ( @class_exists( $class ) ) {
				/** @var HackGuard\Scan\Controller\Base $oObj */
				$oObj = new $class();
				$this->aScanCons[ $slug ] = $oObj->setMod( $this );
			}
			else {
				$this->aScanCons[ $slug ] = false;
			}
		}
		return $this->aScanCons[ $slug ];
	}

	public function getMainWpData() :array {
		$issues = ( new HackGuard\Lib\Reports\Query\ScanCounts() )->setMod( $this );
		$issues->notified = null;
		return array_merge( parent::getMainWpData(), [
			'scan_issues' => array_filter( $issues->all() )
		] );
	}

	protected function handleModAction( string $sAction ) {
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

	protected function preProcessOptions() {
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();

		$this->cleanFileExclusions();

		if ( count( $opts->getFilesToLock() ) === 0 || !$this->getCon()
															 ->getModule_Plugin()
															 ->getShieldNetApiController()
															 ->canHandshake() ) {
			$opts->setOpt( 'file_locker', [] );
			$this->getFileLocker()->purge();
		}

		$lockFiles = $opts->getFilesToLock();
		if ( in_array( 'root_webconfig', $lockFiles ) && !Services::Data()->isWindows() ) {
			unset( $lockFiles[ array_search( 'root_webconfig', $lockFiles ) ] );
			$opts->setOpt( 'file_locker', $lockFiles );
		}

		foreach ( $this->getAllScanCons() as $con ) {
			if ( !$con->isEnabled() ) {
				$con->purge();
			}
		}
	}

	/**
	 * @return int[] - key is scan slug
	 */
	public function getLastScansAt() :array {
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
	 * @param string $scan ptg, wcf, ufc, wpv
	 * @return int
	 */
	public function getLastScanAt( $scan ) {
		/** @var Shield\Databases\Events\Select $oSel */
		$oSel = $this->getCon()
					 ->getModule_Events()
					 ->getDbHandler_Events()
					 ->getQuerySelector();
		$oEntry = $oSel->getLatestForEvent( $scan.'_scan_run' );
		return ( $oEntry instanceof Shield\Databases\Events\EntryVO ) ? $oEntry->created_at : 0;
	}

	/**
	 * @return $this
	 */
	protected function setCustomCronSchedules() {
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();
		$freq = $opts->getScanFrequency();
		Services::WpCron()
				->addNewSchedule(
					$this->prefix( sprintf( 'per-day-%s', $freq ) ),
					[
						'interval' => DAY_IN_SECONDS/$freq,
						'display'  => sprintf( __( '%s per day', 'wp-simple-firewall' ), $freq )
					]
				);
		return $this;
	}

	protected function cleanFileExclusions() {
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();
		$aExclusions = [];

		$aToClean = $opts->getOpt( 'ufc_exclusions', [] );
		if ( is_array( $aToClean ) ) {
			foreach ( $aToClean as $nKey => $sExclusion ) {
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
		}

		$opts->setOpt( 'ufc_exclusions', array_unique( $aExclusions ) );
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

	public function isPtgEnabled() :bool {
		$opts = $this->getOptions();
		return $this->isModuleEnabled() && $this->isPremium()
			   && $opts->isOpt( 'ptg_enable', 'enabled' )
			   && $opts->isOptReqsMet( 'ptg_enable' )
			   && $this->canCacheDirWrite();
	}

	public function insertCustomJsVars_Admin() {
		parent::insertCustomJsVars_Admin();

		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();
		if ( Services::WpPost()->isCurrentPage( 'plugins.php' )
			 && $opts->isPtgReinstallLinks() && $this->getScanCon( 'ptg' )->isReady() ) {
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
	 * @return string|false
	 */
	public function getPtgSnapsBaseDir() {
		return $this->getCon()->getPluginCachePath( 'ptguard/' );
	}

	public function hasWizard() :bool {
		return false;
	}

	/**
	 * @return string
	 */
	public function getTempDir() {
		$sDir = $this->getCon()->getPluginCachePath( 'scans' );
		return Services::WpFs()->mkdir( $sDir ) ? $sDir : false;
	}

	public function getDbHandler_FileLocker() :Shield\Databases\FileLocker\Handler {
		return $this->getDbH( 'file_protect' );
	}

	public function getDbHandler_ScanQueue() :Shield\Databases\ScanQueue\Handler {
		return $this->getDbH( 'scanq' );
	}

	public function getDbHandler_ScanResults() :Shield\Databases\Scanner\Handler {
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
		$this->getDbHandler_ScanQueue()->tableDelete();
		$this->getDbHandler_ScanResults()->tableDelete();
		// 2. Clean out the file locker
		$this->getFileLocker()->purge();
	}

	protected function getNamespaceBase() :string {
		return 'HackGuard';
	}
}