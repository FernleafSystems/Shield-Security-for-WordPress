<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Database;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Core\Databases\{
	Base\Handler,
	Common
};
use FernleafSystems\Wordpress\Plugin\Core\Databases\Ops\TableIndices;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\{
	ActivityLogs,
	ActivityLogsMeta,
	BotSignal,
	CrowdSecSignals,
	Event,
	FileLocker,
	IpMeta,
	IpRules,
	IPs,
	Malware,
	Mfa,
	Reports,
	ReqLogs,
	ResultItemMeta,
	ResultItems,
	Rules,
	ScanItems,
	ScanResults,
	Scans,
	Snapshots,
	UserMeta,
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property ActivityLogs\Ops\Handler     $activity_logs
 * @property ActivityLogsMeta\Ops\Handler $activity_logs_meta
 * @property Snapshots\Ops\Handler        $activity_snapshots
 * @property BotSignal\Ops\Handler        $bot_signals
 * @property CrowdSecSignals\Ops\Handler  $crowdsec_signals
 * @property Event\Ops\Handler            $events
 * @property FileLocker\Ops\Handler       $file_locker
 * @property IPs\Ops\Handler              $ips
 * @property IpMeta\Ops\Handler           $ip_meta
 * @property IpRules\Ops\Handler          $ip_rules
 * @property Malware\Ops\Handler          $malware
 * @property Mfa\Ops\Handler              $mfa
 * @property Reports\Ops\Handler          $reports
 * @property ReqLogs\Ops\Handler          $req_logs
 * @property Rules\Ops\Handler            $rules
 * @property ResultItems\Ops\Handler      $scan_result_items
 * @property ResultItemMeta\Ops\Handler   $scan_result_item_meta
 * @property Scans\Ops\Handler            $scans
 * @property ScanItems\Ops\Handler        $scan_items
 * @property ScanResults\Ops\Handler      $scan_results
 * @property UserMeta\Ops\Handler         $user_meta
 */
class DbCon extends DynPropertiesClass {

	use ExecOnce;
	use PluginCronsConsumer;
	use PluginControllerConsumer;

	public const MAP = [
		'activity_logs'         => [
			'slug'          => 'at_logs',
			'handler_class' => ActivityLogs\Ops\Handler::class,
		],
		'activity_logs_meta'    => [
			'slug'          => 'at_meta',
			'handler_class' => ActivityLogsMeta\Ops\Handler::class,
		],
		'activity_snapshots'    => [
			'slug'          => 'snapshots',
			'handler_class' => Snapshots\Ops\Handler::class,
		],
		'bot_signals'           => [
			'slug'          => 'botsignal',
			'handler_class' => BotSignal\Ops\Handler::class,
		],
		'crowdsec_signals'      => [
			'slug'          => 'crowdsec_signals',
			'handler_class' => CrowdSecSignals\Ops\Handler::class,
		],
		'events'                => [
			'slug'          => 'event',
			'handler_class' => Event\Ops\Handler::class,
		],
		'file_locker'           => [
			'slug'          => 'file_locker',
			'handler_class' => FileLocker\Ops\Handler::class,
		],
		'ips'                   => [
			'slug'          => 'ips',
			'handler_class' => IPs\Ops\Handler::class,
		],
		'ip_meta'               => [
			'slug'          => 'ip_meta',
			'handler_class' => IpMeta\Ops\Handler::class,
		],
		'ip_rules'              => [
			'slug'          => 'ip_rules',
			'handler_class' => IpRules\Ops\Handler::class,
		],
		'malware'               => [
			'slug'          => 'malware',
			'handler_class' => Malware\Ops\Handler::class,
		],
		'mfa'                   => [
			'slug'          => 'mfa',
			'handler_class' => Mfa\Ops\Handler::class,
		],
		'reports'               => [
			'slug'          => 'reports',
			'handler_class' => Reports\Ops\Handler::class,
		],
		'req_logs'              => [
			'slug'          => 'req_logs',
			'handler_class' => ReqLogs\Ops\Handler::class,
		],
		'scan_result_items'     => [
			'slug'          => 'resultitems',
			'handler_class' => ResultItems\Ops\Handler::class,
		],
		'scan_result_item_meta' => [
			'slug'          => 'resultitem_meta',
			'handler_class' => ResultItemMeta\Ops\Handler::class,
		],
		'rules'                 => [
			'slug'          => 'rules',
			'handler_class' => Rules\Ops\Handler::class,
		],
		'scans'                 => [
			'slug'          => 'scans',
			'handler_class' => Scans\Ops\Handler::class,
		],
		'scan_items'            => [
			'slug'          => 'scanitems',
			'handler_class' => ScanItems\Ops\Handler::class,
		],
		'scan_results'          => [
			'slug'          => 'scanresults',
			'handler_class' => ScanResults\Ops\Handler::class,
		],
		'user_meta'             => [
			'slug'          => 'user_meta',
			'handler_class' => UserMeta\Ops\Handler::class,
		],
	];

	/**
	 * @var ?|array
	 */
	private $dbHandlers = null;

	protected function run() {
		$this->setupCronHooks();
	}

	public function runDailyCron() {
		( new CleanDatabases() )->all();
		( new TableIndices( $this->ip_rules->getTableSchema() ) )->applyFromSchema();
	}

	public function runHourlyCron() {
		( new CleanIpRules() )->cleanAutoBlocks();
	}

	/**
	 * @return array[]
	 */
	public function getHandlers() :array {
		if ( $this->dbHandlers === null ) {
			$this->dbHandlers = [];
			$dbSpecs = self::con()->cfg->configuration->databases;
			foreach ( self::MAP as $dbKey => $dbDef ) {
				$dbDef[ 'def' ] = $dbSpecs[ 'tables' ][ $dbKey ];
				$dbDef[ 'handler' ] = null;
				$this->dbHandlers[ $dbKey ] = $dbDef;
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
				$this->load( $dbhKey );
			}
			catch ( \Exception $exception ) {
			}
		}
		return $this->getHandlers();
	}

	/**
	 * @return Handler|mixed|null
	 */
	public function load( string $dbKey ) {
		return $this->loadDbH( $this->getHandlers()[ $dbKey ][ 'slug' ] );
	}

	/**
	 * @return Handler|mixed|null
	 */
	public function loadDbH( string $dbSlug, bool $reload = false ) {
		$con = self::con();

		$dbKey = null;
		foreach ( $this->getHandlers() as $key => $handlerSpec ) {
			if ( $handlerSpec[ 'slug' ] === $dbSlug ) {
				$dbKey = $key;
				$dbh = $handlerSpec;
				break;
			}
		}

		if ( empty( $dbKey ) ) {
			throw new \Exception( '' );
		}

		if ( $reload || empty( $dbh[ 'handler' ] ) ) {
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

			/** @var Handler|mixed $dbh */
			$dbh = new $dbh[ 'handler_class' ]( $dbDef );
			$dbh->use_table_ready_cache = !$con->plugin_reset
										  && $con->comps->opts_lookup->getActivatedPeriod() > Common\TableReadyCache::READY_LIFETIME
										  && ( Services::Request()->ts()
											   - $con->plugin->getTracking()->last_upgrade_at > 10 );
			$dbh->execute();

			$this->dbHandlers[ $dbKey ][ 'handler' ] = $dbh;
		}

		return $this->dbHandlers[ $dbKey ][ 'handler' ];
	}

	public function reset() :void {
		$this->dbHandlers = null;
	}

	public function __get( string $key ) {
		$value = parent::__get( $key );

		if ( isset( self::MAP[ $key ] ) ) {
			$value = $this->load( $key );
		}

		return $value;
	}

	/**
	 * @deprecated 19.2 - required for upgrade from 19.0
	 */
	public function dbhFileLocker() :FileLocker\Ops\Handler {
		return $this->loadDbH( 'file_locker' );
	}
}