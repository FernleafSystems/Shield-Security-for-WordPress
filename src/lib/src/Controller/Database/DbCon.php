<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Database;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Core\Databases\{
	Base\Handler,
	Common
};
use FernleafSystems\Wordpress\Plugin\Core\Databases\Ops\TableIndices;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\{
	Event,
	FileLocker,
	Malware,
	Mfa,
	Reports,
	ResultItemMeta,
	ResultItems,
	ScanItems,
	ScanResults,
	Scans,
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\{
	Logs,
	Meta,
	Snapshots
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\{
	IpMeta,
	IPs,
	ReqLogs,
	UserMeta
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\Rules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\{
	BotSignal,
	CrowdSecSignals,
	IpRules,
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class DbCon {

	use ExecOnce;
	use PluginCronsConsumer;
	use PluginControllerConsumer;

	/**
	 * @var ?|array
	 */
	private $dbHandlers = null;

	protected function run() {
		$this->setupCronHooks();
	}

	public function runDailyCron() {
		( new CleanDatabases() )->all();
		( new TableIndices( $this->dbhIPRules()->getTableSchema() ) )->applyFromSchema();
	}

	public function runHourlyCron() {
		( new CleanIpRules() )->cleanAutoBlocks();
	}

	public function dbhActivityLogs() :Logs\Ops\Handler {
		return $this->loadDbH( 'at_logs' );
	}

	public function dbhActivityLogsMeta() :Meta\Ops\Handler {
		return $this->loadDbH( 'at_meta' );
	}

	public function dbhEvents() :Event\Ops\Handler {
		return $this->loadDbH( 'event' );
	}

	public function dbhFileLocker() :FileLocker\Ops\Handler {
		return $this->loadDbH( 'file_locker' );
	}

	public function dbhBotSignal() :BotSignal\Ops\Handler {
		return $this->loadDbH( 'botsignal' );
	}

	public function dbhCrowdSecSignals() :CrowdSecSignals\Ops\Handler {
		return $this->loadDbH( 'crowdsec_signals' );
	}

	public function dbhIPs() :IPs\Ops\Handler {
		return $this->loadDbH( 'ips' );
	}

	public function dbhIPMeta() :IpMeta\Ops\Handler {
		return $this->loadDbH( 'ip_meta' );
	}

	public function dbhIPRules() :IpRules\Ops\Handler {
		return $this->loadDbH( 'ip_rules' );
	}

	public function dbhMalware() :Malware\Ops\Handler {
		return $this->loadDbH( 'malware' );
	}

	public function dbhMfa() :Mfa\Ops\Handler {
		return $this->loadDbH( 'mfa' );
	}

	public function dbhReports() :Reports\Ops\Handler {
		return $this->loadDbH( 'reports' );
	}

	public function dbhReqLogs() :ReqLogs\Ops\Handler {
		return $this->loadDbH( 'req_logs' );
	}

	public function dbhRules() :Rules\Ops\Handler {
		return $this->loadDbH( 'rules' );
	}

	public function dbhResultItems() :ResultItems\Ops\Handler {
		return $this->loadDbH( 'resultitems' );
	}

	public function dbhResultItemMeta() :ResultItemMeta\Ops\Handler {
		return $this->loadDbH( 'resultitem_meta' );
	}

	public function dbhScans() :Scans\Ops\Handler {
		return $this->loadDbH( 'scans' );
	}

	public function dbhScanItems() :ScanItems\Ops\Handler {
		return $this->loadDbH( 'scanitems' );
	}

	public function dbhScanResults() :ScanResults\Ops\Handler {
		return $this->loadDbH( 'scanresults' );
	}

	public function dbhSnapshots() :Snapshots\Ops\Handler {
		return $this->loadDbH( 'snapshots' );
	}

	public function dbhUserMeta() :UserMeta\Ops\Handler {
		return $this->loadDbH( 'user_meta' );
	}

	/**
	 * @return array[]
	 */
	public function getHandlers() :array {
		if ( $this->dbHandlers === null ) {
			$this->dbHandlers = [];
			$dbSpecs = self::con()->cfg->configuration->databases;
			foreach ( $dbSpecs[ 'db_handler_classes' ] as $dbKey => $dbClass ) {
				$def = $dbSpecs[ 'db_table_'.$dbKey ];
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
	 * @return Handler|mixed|null
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
				if ( ( $colDef[ 'macro_type' ] ?? '' ) === Common\Types::MACROTYPE_FOREIGN_KEY_ID ) {
					$table = $colDef[ 'foreign_key' ][ 'ref_table' ];
					if ( \str_starts_with( $table, $con->getPluginPrefix( '_' ) ) ) {
						$this->loadDbH( \str_replace( $con->getPluginPrefix( '_' ).'_', '', $table ) );
					}
				}
			}

			$dbDef[ 'table_prefix' ] = $con->getPluginPrefix( '_' );

			$modPlug = $con->getModule_Plugin();
			/** @var Handler|mixed $dbh */
			$dbh = new $dbh[ 'class' ]( $dbDef );
			$dbh->use_table_ready_cache = $modPlug->getActivateLength() > Common\TableReadyCache::READY_LIFETIME
										  &&
										  ( Services::Request()->ts() - $modPlug->getTracking()->last_upgrade_at > 10 );
			$dbh->execute();

			$this->dbHandlers[ $dbKey ][ 'handler' ] = $dbh;
		}

		return $this->dbHandlers[ $dbKey ][ 'handler' ];
	}
}