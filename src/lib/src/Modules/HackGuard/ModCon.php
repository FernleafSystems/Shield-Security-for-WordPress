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
			$this->oFileLocker = ( new Lib\FileLocker\FileLockerController() )->setMod( $this );
		}
		return $this->oFileLocker;
	}

	public function getScansCon() :Scan\ScansController {
		if ( !isset( $this->scanCon ) ) {
			$this->scanCon = ( new Scan\ScansController() )->setMod( $this );
		}
		return $this->scanCon;
	}

	public function getScanQueueController() :Scan\Queue\Controller {
		if ( !isset( $this->scanQueueCon ) ) {
			$this->scanQueueCon = ( new Scan\Queue\Controller() )->setMod( $this );
		}
		return $this->scanQueueCon;
	}

	public function getDbH_Scans() :DB\Scans\Ops\Handler {
		return $this->getDbHandler()->loadDbH( 'scans' );
	}

	public function getDbH_ScanItems() :DB\ScanItems\Ops\Handler {
		$this->getDbH_Scans();
		return $this->getDbHandler()->loadDbH( 'scanitems' );
	}

	public function getDbH_ResultItems() :DB\ResultItems\Ops\Handler {
		return $this->getDbHandler()->loadDbH( 'resultitems' );
	}

	public function getDbH_ResultItemMeta() :DB\ResultItemMeta\Ops\Handler {
		$this->getDbH_ResultItems();
		return $this->getDbHandler()->loadDbH( 'resultitem_meta' );
	}

	public function getDbH_ScanResults() :DB\ScanResults\Ops\Handler {
		$this->getDbH_Scans();
		$this->getDbH_ResultItems();
		return $this->getDbHandler()->loadDbH( 'scanresults' );
	}

	/**
	 * @return Scan\Controller\Base|mixed
	 */
	public function getScanCon( string $slug ) {
		return $this->getScansCon()->getScanCon( $slug );
	}

	public function getMainWpData() :array {
		return array_merge( parent::getMainWpData(), [
			'scan_issues' => array_filter( ( new Shield\Modules\HackGuard\Scan\Results\Counts() )->setMod( $this )->all() )
		] );
	}

	protected function preProcessOptions() {
		/** @var Options $opts */
		$opts = $this->getOptions();

		if ( $opts->isOptChanged( 'scan_frequency' ) ) {
			$this->getScansCon()->deleteCron();
		}

		$lockFiles = $opts->getFilesToLock();
		if ( in_array( 'root_webconfig', $lockFiles ) && !Services::Data()->isWindows() ) {
			unset( $lockFiles[ array_search( 'root_webconfig', $lockFiles ) ] );
			$opts->setOpt( 'file_locker', $lockFiles );
		}

		if ( count( $opts->getFilesToLock() ) === 0 || !$this->getCon()
															 ->getModule_Plugin()
															 ->getShieldNetApiController()
															 ->canHandshake() ) {
			$opts->setOpt( 'file_locker', [] );
			$this->getFileLocker()->purge();
		}

		foreach ( $this->getScansCon()->getAllScanCons() as $con ) {
			if ( !$con->isEnabled() ) {
				$con->purge();
			}
		}

		$this->cleanScanExclusions();
	}

	private function cleanScanExclusions() {
		/** @var Options $opts */
		$opts = $this->getOptions();

		$specialDirs = array_map( 'trailingslashit', [
			ABSPATH,
			path_join( ABSPATH, 'wp-admin' ),
			path_join( ABSPATH, 'wp-includes' ),
			untrailingslashit( WP_CONTENT_DIR ),
			path_join( WP_CONTENT_DIR, 'plugins' ),
			path_join( WP_CONTENT_DIR, 'themes' ),
		] );

		$values = $opts->getOpt( 'scan_path_exclusions', [] );
		$opts->setOpt( 'scan_path_exclusions',
			( new Shield\Modules\Base\Options\WildCardOptions() )->clean(
				is_array( $values ) ? $values : [],
				$specialDirs,
				Shield\Modules\Base\Options\WildCardOptions::FILE_PATH_REL
			)
		);
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
					$this->getCon()->prefix( sprintf( 'per-day-%s', $freq ) ),
					[
						'interval' => DAY_IN_SECONDS/$freq,
						'display'  => sprintf( __( '%s per day', 'wp-simple-firewall' ), $freq )
					]
				);
		return $this;
	}

	public function getScansTempDir() :string {
		return $this->getCon()->cache_dir_handler->buildSubDir( 'scans' );
	}

	public function getDbHandler_FileLocker() :Databases\FileLocker\Handler {
		return $this->getDbH( 'filelocker' );
	}

	protected function cleanupDatabases() {
		( new Shield\Modules\HackGuard\DB\Utility\Clean() )
			->setMod( $this )
			->execute();
	}

	/**
	 * @throws \Exception
	 */
	protected function isReadyToExecute() :bool {
		return $this->getDbH_ScanResults()->isReady() && $this->getDbH_ScanItems()->isReady()
			   && parent::isReadyToExecute();
	}

	public function onPluginDeactivate() {
		// 1. Clean out the scanners
		/** @var Options $opts */
		$opts = $this->getOptions();
		foreach ( $opts->getScanSlugs() as $slug ) {
			$this->getScanCon( $slug )->purge();
		}
		$this->getDbH_ScanItems()->tableDelete();
		$this->getDbH_ScanResults()->tableDelete();
		// 2. Clean out the file locker
		$this->getFileLocker()->purge();
	}

	public function runDailyCron() {
		parent::runDailyCron();

		$carbon = Services::Request()->carbon();
		if ( $carbon->isSunday() ) {
			( new Shield\Scans\Afs\Processing\FileScanOptimiser() )
				->setMod( $this )
				->cleanStaleHashesOlderThan( $carbon->subWeek()->timestamp );
		}
	}

	/**
	 * @inheritDoc
	 * @deprecated 13.1
	 */
	public function getDbHandlers( $bInitAll = false ) {
		return [];
	}
}