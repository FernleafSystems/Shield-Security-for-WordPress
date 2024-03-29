<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\DBs;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Processing\FileScanOptimiser;
use FernleafSystems\Wordpress\Services\Services;

class ModCon extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\ModCon {

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

	public function onWpInit() {
		parent::onWpInit();
		self::con()->comps->scans_queue->execute();
	}

	public function getFileLocker() :Lib\FileLocker\FileLockerController {
		return self::con()->comps !== null ? self::con()->comps->file_locker :
			( $this->oFileLocker ?? $this->oFileLocker = new Lib\FileLocker\FileLockerController() );
	}

	public function getScansCon() :Scan\ScansController {
		return self::con()->comps !== null ? self::con()->comps->scans :
			( $this->scanCon ?? $this->scanCon = new Scan\ScansController() );
	}

	public function getScanQueueController() :Scan\Queue\Controller {
		return self::con()->comps !== null ? self::con()->comps->scans_queue :
			( $this->scanQueueCon ?? $this->scanQueueCon = new Scan\Queue\Controller() );
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