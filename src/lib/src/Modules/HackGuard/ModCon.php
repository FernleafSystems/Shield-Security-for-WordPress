<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class ModCon extends BaseShield\ModCon {

	public const SLUG = 'hack_protect';

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
		return $this->oFileLocker ?? $this->oFileLocker = ( new Lib\FileLocker\FileLockerController() )->setMod( $this );
	}

	public function getScansCon() :Scan\ScansController {
		return $this->scanCon ?? $this->scanCon = ( new Scan\ScansController() )->setMod( $this );
	}

	public function getScanQueueController() :Scan\Queue\Controller {
		return $this->scanQueueCon ?? $this->scanQueueCon = ( new Scan\Queue\Controller() )->setMod( $this );
	}

	public function getDbH_FileLocker() :DB\FileLocker\Ops\Handler {
		return $this->getDbHandler()->loadDbH( 'file_locker' );
	}

	public function getDbH_Malware() :DB\Malware\Ops\Handler {
		return $this->getDbHandler()->loadDbH( 'malware' );
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

	protected function setCustomCronSchedules() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$freq = $opts->getScanFrequency();
		Services::WpCron()->addNewSchedule(
			$this->getCon()->prefix( sprintf( 'per-day-%s', $freq ) ),
			[
				'interval' => DAY_IN_SECONDS/$freq,
				'display'  => sprintf( __( '%s per day', 'wp-simple-firewall' ), $freq )
			]
		);
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
		return $this->getDbH_ScanResults()->isReady() && $this->getDbH_ScanItems()->isReady();
	}

	public function onPluginDeactivate() {
		// 1. Clean out the scanners
		foreach ( $this->getScansCon()->getAllScanCons() as $scanCon ) {
			$scanCon->purge();
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

		( new Lib\Utility\CleanOutOldGuardFiles() )
			->setMod( $this )
			->execute();
	}
}