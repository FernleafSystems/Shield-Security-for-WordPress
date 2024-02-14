<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Database;

use FernleafSystems\Wordpress\Plugin\Core\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\{
	Logs,
	Meta,
	Snapshots
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\{
	IPs,
	IpMeta,
	UserMeta,
	ReqLogs
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\DB\Event;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Hackguard\DB\{
	FileLocker,
	Malware,
	Scans,
	ScanItems,
	ResultItems,
	ResultItemMeta,
	ScanResults,
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\{
	BotSignal,
	CrowdSecSignals,
	IpRules,
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\DB\Mfa;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\Rules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\DB\Reports;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class DbCon {

	use PluginControllerConsumer;

	/**
	 * @var ?|array
	 */
	private $dbHandlers = null;

	public function dbhActivityLogs() :Logs\Ops\Handler {
		return $this->loadDbH( 'at_logs' );
	}

	public function dbhActivityLogsMeta() :Meta\Ops\Handler {
		return $this->loadDbH( 'at_meta' );
	}

	public function dbhSnapshots() :Snapshots\Ops\Handler {
		return $this->loadDbH( 'snapshots' );
	}

	public function dbhRules() :Rules\Ops\Handler {
		return $this->loadDbH( 'rules' );
	}

	public function dbhIPs() :IPs\Ops\Handler {
		return $this->loadDbH( 'ips' );
	}

	public function dbhIPMeta() :IpMeta\Ops\Handler {
		return $this->loadDbH( 'ip_meta' );
	}

	public function dbhUserMeta() :UserMeta\Ops\Handler {
		return $this->loadDbH( 'user_meta' );
	}

	public function dbhReqLogs() :ReqLogs\Ops\Handler {
		return $this->loadDbH( 'req_logs' );
	}

	public function dbhEvents() :Event\Ops\Handler {
		return $this->loadDbH( 'event' );
	}

	public function dbhFileLocker() :FileLocker\Ops\Handler {
		return $this->loadDbH( 'file_locker' );
	}

	public function dbhMalware() :Malware\Ops\Handler {
		return $this->loadDbH( 'malware' );
	}

	public function dbhScans() :Scans\Ops\Handler {
		return $this->loadDbH( 'scans' );
	}

	public function dbhScanItems() :ScanItems\Ops\Handler {
		return $this->loadDbH( 'scanitems' );
	}

	public function dbhResultItems() :ResultItems\Ops\Handler {
		return $this->loadDbH( 'resultitems' );
	}

	public function dbhResultItemMeta() :ResultItemMeta\Ops\Handler {
		return $this->loadDbH( 'resultitem_meta' );
	}

	public function dbhScanResults() :ScanResults\Ops\Handler {
		return $this->loadDbH( 'scanresults' );
	}

	public function dbhBotSignal() :BotSignal\Ops\Handler {
		return $this->loadDbH( 'botsignal' );
	}

	public function dbhIPRules() :IpRules\Ops\Handler {
		return $this->loadDbH( 'ip_rules' );
	}

	public function dbhCrowdSecSignals() :CrowdSecSignals\Ops\Handler {
		return $this->loadDbH( 'crowdsec_signals' );
	}

	public function dbhMfa() :Mfa\Ops\Handler {
		return $this->loadDbH( 'mfa' );
	}

	public function dbhReports() :Reports\Ops\Handler {
		return $this->loadDbH( 'reports' );
	}

	/**
	 * @return array[]
	 */
	public function getHandlers() :array {
		if ( $this->dbHandlers === null ) {
			$this->dbHandlers = [];
			$opts = self::con()->getModule_Data()->opts();
			$classes = $opts->getDef( 'db_handler_classes' );
			foreach ( \is_array( $classes ) ? $classes : [] as $dbKey => $dbClass ) {
				$def = $opts->getDef( 'db_table_'.$dbKey );
				$this->dbHandlers[ $dbKey ] = [
					'name'    => $def[ 'name' ] ?? $dbKey,
					'class'   => $dbClass,
					'def'     => $def,
					'handler' => null,
				];
			}
		}
		return $this->dbHandlers;
	}

	/**
	 * @return array[]
	 */
	public function loadAll() :array {
		foreach ( \array_keys( $this->getHandlers() ) as $dbhKey ) {
			try {
				$this->loadDbH( $dbhKey );
			}
			catch ( \Exception $exception ) {
			}
		}
		return $this->getHandlers();
	}

	/**
	 * @return Databases\Base\Handler|mixed|null
	 * @throws \Exception
	 */
	public function loadDbH( string $dbKey, bool $reload = false ) {
		$con = self::con();

		$dbh = $this->getHandlers()[ $dbKey ] ?? null;
		if ( empty( $dbh ) ) {
			throw new \Exception( sprintf( 'Invalid DBH Key %s', $dbKey ) );
		}

		if ( $reload || empty( $dbh[ 'handler' ] ) ) {

			if ( empty( $dbh[ 'class' ] ) ) {
				throw new \Exception( sprintf( 'DB Handler Class for key (%s) is not specified.', $dbKey ) );
			}
			if ( !\class_exists( $dbh[ 'class' ] ) ) {
				throw new \Exception( sprintf( 'DB Handler for key (%s) is not valid', $dbKey ) );
			}
			if ( empty( $dbh[ 'def' ] ) ) {
				throw new \Exception( sprintf( 'DB Definition for key (%s) is empty', $dbKey ) );
			}

			/**
			 * We need to ensure that any dependent (foreign key references) tables are initiated before
			 * attempting to initiate ourselves.
			 */
			$dbDef = $dbh[ 'def' ];
			foreach ( $dbDef[ 'cols_custom' ] as $colDef ) {
				if ( ( $colDef[ 'macro_type' ] ?? '' ) === Databases\Common\Types::MACROTYPE_FOREIGN_KEY_ID ) {
					$table = $colDef[ 'foreign_key' ][ 'ref_table' ];
					if ( \str_starts_with( $table, $con->getPluginPrefix( '_' ) ) ) {
						$this->loadDbH( \str_replace( $con->getPluginPrefix( '_' ).'_', '', $table ) );
					}
				}
			}

			$dbDef[ 'table_prefix' ] = $con->getPluginPrefix( '_' );

			$modPlug = $con->getModule_Plugin();
			/** @var Databases\Base\Handler|mixed $dbh */
			$dbh = new $dbh[ 'class' ]( $dbDef );
			$dbh->use_table_ready_cache = $modPlug->getActivateLength() > Databases\Common\TableReadyCache::READY_LIFETIME
										  &&
										  ( Services::Request()->ts() - $modPlug->getTracking()->last_upgrade_at > 10 );
			$dbh->execute();

			$this->dbHandlers[ $dbKey ][ 'handler' ] = $dbh;
		}

		return $this->dbHandlers[ $dbKey ][ 'handler' ];
	}
}