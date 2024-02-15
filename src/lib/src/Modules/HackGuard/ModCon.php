<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\DBs;
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

	public function onConfigChanged() :void {
		/** @var Options $opts */
		$opts = $this->opts();

		if ( $opts->isOptChanged( 'scan_frequency' ) ) {
			$this->getScansCon()->deleteCron();
		}

		if ( $opts->isOptChanged( 'file_locker' ) ) {
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
		return self::con()->db_con->dbhScanResults()->isReady() && self::con()->db_con->dbhScanItems()->isReady();
	}

	public function onPluginDeactivate() {
		// 1. Clean out the scanners
		foreach ( $this->getScansCon()->getAllScanCons() as $scanCon ) {
			$scanCon->purge();
		}
		self::con()->db_con->dbhScanItems()->tableDelete();
		self::con()->db_con->dbhScanResults()->tableDelete();
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
	 * @deprecated 19.1
	 */
	public function getDbH_FileLocker() :DB\FileLocker\Ops\Handler {
		return self::con()->db_con->loadDbH( 'file_locker' );
	}

	/**
	 * @deprecated 19.1
	 */
	public function getDbH_Malware() :DBs\Malware\Ops\Handler {
		return self::con()->db_con->loadDbH( 'malware' );
	}

	/**
	 * @deprecated 19.1
	 */
	public function getDbH_Scans() :DBs\Scans\Ops\Handler {
		return self::con()->db_con->loadDbH( 'scans' );
	}

	/**
	 * @deprecated 19.1
	 */
	public function getDbH_ScanItems() :DBs\ScanItems\Ops\Handler {
		return self::con()->db_con->loadDbH( 'scanitems' );
	}

	/**
	 * @deprecated 19.1
	 */
	public function getDbH_ResultItems() :DBs\ResultItems\Ops\Handler {
		return self::con()->db_con->loadDbH( 'resultitems' );
	}

	/**
	 * @deprecated 19.1
	 */
	public function getDbH_ResultItemMeta() :DBs\ResultItemMeta\Ops\Handler {
		return self::con()->db_con->loadDbH( 'resultitem_meta' );
	}

	/**
	 * @deprecated 19.1
	 */
	public function getDbH_ScanResults() :DBs\ScanResults\Ops\Handler {
		return self::con()->db_con->loadDbH( 'scanresults' );
	}
}