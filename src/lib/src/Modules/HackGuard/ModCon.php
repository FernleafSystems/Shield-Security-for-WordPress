<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class ModCon extends BaseShield\ModCon {

	/**
	 * @var Scan\ScansController
	 */
	private $scanCon;

	/**
	 * @var Scan\Queue\Controller
	 */
	private $scanQueueCon;

	/**
	 * @var Scan\Controller\Base[]
	 */
	private $scansCons;

	/**
	 * @var Lib\FileLocker\FileLockerController
	 */
	private $oFileLocker;

	protected function doPostConstruction() {
		$this->setCustomCronSchedules();
	}

	public function onWpInit() {
		parent::onWpInit();
		$this->getScanQueueController();
	}

	public function getFileLocker() :Lib\FileLocker\FileLockerController {
		if ( !isset( $this->oFileLocker ) ) {
			$this->oFileLocker = ( new Lib\FileLocker\FileLockerController() )
				->setMod( $this );
		}
		return $this->oFileLocker;
	}

	public function getScansCon() :Scan\ScansController {
		if ( !isset( $this->scanCon ) ) {
			$this->scanCon = ( new Scan\ScansController() )
				->setMod( $this );
		}
		return $this->scanCon;
	}

	public function getScanQueueController() :Scan\Queue\Controller {
		if ( !isset( $this->scanQueueCon ) ) {
			$this->scanQueueCon = ( new Scan\Queue\Controller() )
				->setMod( $this );
		}
		return $this->scanQueueCon;
	}

	/**
	 * @return Scan\Controller\Base[]
	 * @deprecated 10.1
	 */
	public function getAllScanCons() :array {
		return $this->scansCons ?? $this->getScansCon()->getAllScanCons();
	}

	/**
	 * @param string $slug
	 * @return Scan\Controller\Base|mixed
	 * @throws \Exception
	 */
	public function getScanCon( string $slug ) {
		return empty( $this->scansCons[ $slug ] ) ?
			$this->getScansCon()->getScanCon( $slug ) : $this->scansCons[ $slug ];
	}

	public function getMainWpData() :array {
		$issues = ( new Lib\Reports\Query\ScanCounts() )->setMod( $this );
		$issues->notified = null;
		return array_merge( parent::getMainWpData(), [
			'scan_issues' => array_filter( $issues->all() )
		] );
	}

	protected function handleModAction( string $action ) {
		switch ( $action ) {
			case  'scan_file_download':
				( new Lib\Utility\FileDownloadHandler() )
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
		/** @var Options $opts */
		$opts = $this->getOptions();

		$this->cleanFileExclusions();

		if ( $opts->isOptChanged( 'scan_frequency' ) ) {
			$this->getScansCon()->deleteCron();
		}

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

		foreach ( $this->getScansCon()->getAllScanCons() as $con ) {
			if ( !$con->isEnabled() ) {
				$con->purge();
			}
		}
	}

	/**
	 * @return $this
	 */
	protected function setCustomCronSchedules() {
		/** @var Options $opts */
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
		/** @var Options $opts */
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

	public function isPtgEnabled() :bool {
		$opts = $this->getOptions();
		return $this->isModuleEnabled() && $this->isPremium()
			   && $opts->isOpt( 'ptg_enable', 'enabled' )
			   && $opts->isOptReqsMet( 'ptg_enable' )
			   && $this->canCacheDirWrite();
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

	public function getDbHandler_FileLocker() :Databases\FileLocker\Handler {
		return $this->getDbH( 'file_protect' );
	}

	public function getDbHandler_ScanQueue() :Databases\ScanQueue\Handler {
		return $this->getDbH( 'scanq' );
	}

	public function getDbHandler_ScanResults() :Databases\Scanner\Handler {
		return $this->getDbH( 'scanresults' );
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function isReadyToExecute() :bool {
		return ( $this->getDbHandler_ScanQueue() instanceof Databases\ScanQueue\Handler )
			   && $this->getDbHandler_ScanQueue()->isReady()
			   && ( $this->getDbHandler_ScanResults() instanceof Databases\Scanner\Handler )
			   && $this->getDbHandler_ScanQueue()->isReady()
			   && parent::isReadyToExecute();
	}

	public function onPluginDeactivate() {
		// 1. Clean out the scanners
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();
		foreach ( $oOpts->getScanSlugs() as $slug ) {
			$this->getScanCon( $slug )->purge();
		}
		$this->getDbHandler_ScanQueue()->tableDelete();
		$this->getDbHandler_ScanResults()->tableDelete();
		// 2. Clean out the file locker
		$this->getFileLocker()->purge();
	}

	/**
	 * @return bool
	 * @deprecated 10.1
	 */
	public function isWpvulnPluginsHighlightEnabled() :bool {
		return false;
	}
}