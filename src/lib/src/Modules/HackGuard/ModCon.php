<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Processing\FileScanOptimiser;
use FernleafSystems\Wordpress\Services\Services;

class ModCon extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield\ModCon {

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
		return $this->oFileLocker ?? $this->oFileLocker = new Lib\FileLocker\FileLockerController();
	}

	public function getScansCon() :Scan\ScansController {
		return $this->scanCon ?? $this->scanCon = new Scan\ScansController();
	}

	public function getScanQueueController() :Scan\Queue\Controller {
		return $this->scanQueueCon ?? $this->scanQueueCon = new Scan\Queue\Controller();
	}

	public function getDbH_FileLocker() :DB\FileLocker\Ops\Handler {
		return self::con()->db_con->loadDbH( 'file_locker' );
	}

	public function getDbH_Malware() :DB\Malware\Ops\Handler {
		return self::con()->db_con->loadDbH( 'malware' );
	}

	public function getDbH_Scans() :DB\Scans\Ops\Handler {
		return self::con()->db_con->loadDbH( 'scans' );
	}

	public function getDbH_ScanItems() :DB\ScanItems\Ops\Handler {
		return self::con()->db_con->loadDbH( 'scanitems' );
	}

	public function getDbH_ResultItems() :DB\ResultItems\Ops\Handler {
		return self::con()->db_con->loadDbH( 'resultitems' );
	}

	public function getDbH_ResultItemMeta() :DB\ResultItemMeta\Ops\Handler {
		return self::con()->db_con->loadDbH( 'resultitem_meta' );
	}

	public function getDbH_ScanResults() :DB\ScanResults\Ops\Handler {
		return self::con()->db_con->loadDbH( 'scanresults' );
	}

	public function preProcessOptions() {
		/** @var Options $opts */
		$opts = $this->opts();

		if ( $opts->isOptChanged( 'scan_frequency' ) ) {
			$this->getScansCon()->deleteCron();
		}

		$lockFiles = $opts->getFilesToLock();
		if ( !empty( $lockFiles ) ) {
			if ( \in_array( 'root_webconfig', $lockFiles ) && !Services::Data()->isWindows() ) {
				unset( $lockFiles[ \array_search( 'root_webconfig', $lockFiles ) ] );
				$opts->setOpt( 'file_locker', $lockFiles );
			}

			if ( \count( $opts->getFilesToLock() ) === 0 || !self::con()
																 ->getModule_Plugin()
																 ->getShieldNetApiController()
																 ->canHandshake() ) {
				$opts->setOpt( 'file_locker', [] );
				$this->getFileLocker()->purge();
			}
		}

		foreach ( $this->getScansCon()->getAllScanCons() as $con ) {
			if ( !$con->isEnabled() ) {
				$con->purge();
			}
		}
	}

	protected function setCustomCronSchedules() {
		/** @var Options $opts */
		$opts = $this->opts();
		$freq = $opts->getScanFrequency();
		Services::WpCron()->addNewSchedule(
			self::con()->prefix( sprintf( 'per-day-%s', $freq ) ),
			[
				'interval' => \DAY_IN_SECONDS/$freq,
				'display'  => sprintf( __( '%s per day', 'wp-simple-firewall' ), $freq )
			]
		);
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
			( new FileScanOptimiser() )->cleanStaleHashesOlderThan( $carbon->subWeek()->timestamp );
		}

		( new Lib\Utility\CleanOutOldGuardFiles() )->execute();
	}

	/**
	 * @deprecated 18.5
	 */
	private function cleanScanExclusions() {
	}
}