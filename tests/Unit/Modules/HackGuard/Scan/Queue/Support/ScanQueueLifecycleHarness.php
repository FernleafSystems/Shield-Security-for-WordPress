<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Queue\Support;

use Brain\Monkey\Functions;
use Carbon\Carbon;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItems\Ops as ResultItemsDB;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ScanItems\Ops as ScanItemsDB;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops as ScansDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\Build\QueueBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\Controller as QueueController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\QueueProcessor;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\QueueWatchdog;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseScanActionVO;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	CacheStore\CacheStoreTestCacheDir,
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\{
	Db,
	Fs,
	General,
	Plugins,
	Request
};
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;

class ScanQueueLifecycleHarness {

	public Controller $controller;

	public AsyncQueueHarness $async;

	public LifecycleSqliteDb $sql;

	public LifecycleScansDb $scansDb;

	public LifecycleScanItemsDb $scanItemsDb;

	public LifecycleActionRouter $actionRouter;

	private LifecycleQueueComponent $queueComponent;
	private LifecycleEmptyDbHandler $resultItemsDb;

	private int $now;

	private array $itemsByScan;

	/**
	 * @param array<string,string[]> $itemsByScan
	 */
	public function __construct(
		int $now = 1700000000,
		array $itemsByScan = [
			'afs' => [ 'afs-a', 'afs-b' ],
			'apc' => [ 'apc-a' ],
			'wpv' => [ 'wpv-a' ],
		]
	) {
		$this->now = $now;
		$this->itemsByScan = $itemsByScan;
		$this->async = new AsyncQueueHarness();
		$this->sql = new LifecycleSqliteDb( $this->now );
		$this->scansDb = new LifecycleScansDb( $this->sql );
		$this->scanItemsDb = new LifecycleScanItemsDb( $this->sql );
		$this->resultItemsDb = new LifecycleEmptyDbHandler( 'scan_result_items' );
		$this->queueComponent = new LifecycleQueueComponent();
		$this->actionRouter = new LifecycleActionRouter();
	}

	public function install( bool $isWpCli = false ) :self {
		$this->installWordPressFunctions();
		$general = new LifecycleGeneral();
		$general->wpCli = $isWpCli;
		ServicesState::installItems( [
			'service_request'   => new LifecycleRequest( $this->now ),
			'service_wpdb'      => $this->sql,
			'service_wpgeneral' => $general,
			'service_wpplugins' => new LifecyclePlugins(),
		] );
		$this->installController();
		$this->queueComponent->builder = new QueueBuilder();
		$this->queueComponent->processor = new QueueProcessor();
		return $this;
	}

	public function installAfsWorkerEnvironment( string $cacheRoot ) :self {
		Functions\when( 'path_join' )->alias(
			static fn( string $base, string $path ) :string => \rtrim( $base, '/\\' ).'/'.\ltrim( $path, '/\\' )
		);

		$patternsDir = \rtrim( $cacheRoot, '/\\' ).'/scans';
		if ( !\is_dir( $patternsDir ) && !@\mkdir( $patternsDir, 0777, true ) && !\is_dir( $patternsDir ) ) {
			throw new \RuntimeException( 'Failed to create AFS patterns cache directory.' );
		}

		$patterns = \json_encode( [
			'raw'       => [],
			're'        => [],
			'iraw'      => [],
			'functions' => [],
			'keywords'  => [],
		] );
		$compressed = \is_string( $patterns ) ? \gzdeflate( $patterns ) : false;
		$patternsFile = $patternsDir.'/malcache_patterns_v2.txt';
		if ( !\is_string( $compressed )
			 || \file_put_contents( $patternsFile, $compressed ) === false
			 || !\touch( $patternsFile, $this->now ) ) {
			throw new \RuntimeException( 'Failed to prepare AFS patterns cache.' );
		}

		$this->controller->cache_dir_handler = new CacheStoreTestCacheDir( $cacheRoot );
		ServicesState::mergeItems( [
			'service_wpfs' => new LifecycleAfsFs(),
		] );
		return $this;
	}

	public function builder() :QueueBuilder {
		return $this->queueComponent->builder;
	}

	public function processor() :QueueProcessor {
		return $this->queueComponent->processor;
	}

	public function failBuildFor( string $slug ) :self {
		$this->queueComponent->scansComponent->failBuildFor( $slug );
		return $this;
	}

	public function insertScan( array $data ) :int {
		return $this->sql->insertScan( $data );
	}

	public function insertScanItem(
		int $scanID,
		array $items,
		int $startedAt = 0,
		int $finishedAt = 0,
		?int $attempts = null,
		?int $itemCount = null
	) :int {
		return $this->sql->insertScanItem( [
			'scan_ref'    => $scanID,
			'items'       => \base64_encode( \json_encode( $items ) ?: '[]' ),
			'item_count'  => $itemCount ?? \count( $items ),
			'started_at'  => $startedAt,
			'attempts'    => $attempts ?? ( $startedAt > 0 ? 1 : 0 ),
			'finished_at' => $finishedAt,
		] );
	}

	public function scanRow( int $scanID ) :array {
		return $this->sql->scanRow( $scanID );
	}

	public function scanItemRow( int $itemID ) :array {
		return $this->sql->scanItemRow( $itemID );
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function scanRows() :array {
		return $this->sql->scanRows();
	}

	public function countScanItems( int $scanID ) :int {
		return $this->sql->countScanItems( $scanID );
	}

	public function failNextResultItemInsert() :self {
		$this->resultItemsDb->failNextInsert();
		return $this;
	}

	public function resultItemInsertFailureCount() :int {
		return $this->resultItemsDb->countConsumedInsertFailures();
	}

	private function installController() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->cfg = (object)[
			'properties' => [
				'slug_parent' => 'icwp',
				'slug_plugin' => 'wpsf',
			],
		];
		$controller->db_con = (object)[
			'scans'                 => $this->scansDb,
			'scan_items'            => $this->scanItemsDb,
			'scan_results'          => new LifecycleEmptyDbHandler( 'scan_results', $this->sql ),
			'scan_result_items'     => $this->resultItemsDb,
			'scan_result_item_meta' => new LifecycleEmptyDbHandler( 'scan_result_item_meta', $this->sql ),
		];
		$this->queueComponent->scansComponent = new LifecycleScansComponent( $this->itemsByScan );
		$controller->comps = (object)[
			'scans'        => $this->queueComponent->scansComponent,
			'scans_queue'  => $this->queueComponent,
			'events'       => new LifecycleEventsComponent(),
			'opts_lookup'  => new LifecycleOptsLookup(),
			'file_locker'  => new LifecycleFileLocker(),
		];
		$controller->opts = new LifecycleOpts();
		$controller->action_router = $this->actionRouter;
		$this->controller = $controller;
		PluginControllerInstaller::install( $controller );
	}

	private function installWordPressFunctions() :void {
		$async = $this->async;

		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_sql' )->alias(
			static fn( $value ) :string => \addslashes( (string)$value )
		);
		Functions\when( 'wp_json_encode' )->alias(
			static fn( $value ) :string => \json_encode( $value ) ?: 'null'
		);
		Functions\when( 'is_wp_error' )->alias( static fn( $value ) :bool => $value instanceof \WP_Error );
		Functions\when( 'plugins_api' )->alias(
			static fn( string $action, array $args = [] ) :object => (object)[
				'slug'         => (string)( $args[ 'slug' ] ?? '' ),
				'version'      => '1.0.0',
				'last_updated' => '2010-01-01 00:00:00',
			]
		);
		Functions\when( 'absint' )->alias(
			static fn( $value ) :int => \abs( (int)$value )
		);
		Functions\when( 'wp_convert_hr_to_bytes' )->alias(
			static fn( $value ) :int => 1073741824
		);
		Functions\when( 'add_action' )->alias(
			static function ( string $hook, $callback = null, int $priority = 10, int $acceptedArgs = 1 ) use ( $async ) :bool {
				$async->addAction( $hook, $callback, $priority, $acceptedArgs );
				return true;
			}
		);
		Functions\when( 'add_filter' )->alias(
			static function ( string $hook, $callback = null, int $priority = 10, int $acceptedArgs = 1 ) use ( $async ) :bool {
				$async->addFilter( $hook, $callback, $priority, $acceptedArgs );
				return true;
			}
		);
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, $value ) {
				return \substr( $hook, -7 ) === '_wp_die' ? false : $value;
			}
		);
		Functions\when( 'do_action' )->alias(
			static function ( string $hook, ...$args ) use ( $async ) :void {
				$async->doAction( $hook, $args );
			}
		);
		Functions\when( 'wp_next_scheduled' )->alias(
			static fn( string $hook ) => $async->nextScheduled( $hook )
		);
		Functions\when( 'wp_schedule_event' )->alias(
			static function ( int $timestamp, string $recurrence, string $hook ) use ( $async ) :bool {
				$async->scheduleEvent( $timestamp, $hook, $recurrence );
				return true;
			}
		);
		Functions\when( 'wp_schedule_single_event' )->alias(
			static function ( int $timestamp, string $hook ) use ( $async ) :bool {
				$async->scheduleEvent( $timestamp, $hook, 'single' );
				return true;
			}
		);
		Functions\when( 'wp_unschedule_event' )->alias(
			static function ( int $timestamp, string $hook ) use ( $async ) :bool {
				$async->unscheduleEvent( $timestamp, $hook );
				return true;
			}
		);
		Functions\when( 'wp_unschedule_hook' )->alias(
			static function ( string $hook ) use ( $async ) :bool {
				$async->unscheduleHook( $hook );
				return true;
			}
		);
		Functions\when( 'wp_clear_scheduled_hook' )->alias(
			static function ( string $hook ) use ( $async ) :bool {
				$async->unscheduleHook( $hook );
				return true;
			}
		);
		Functions\when( 'wp_remote_post' )->alias(
			static function ( string $url, array $args = [] ) use ( $async ) {
				$async->remotePosts[] = [
					'url'  => $url,
					'args' => $args,
				];
				return $async->remotePostResponse;
			}
		);
		Functions\when( 'wp_create_nonce' )->justReturn( 'unit-nonce' );
		Functions\when( 'admin_url' )->alias(
			static fn( string $path = '' ) :string => 'https://example.test/wp-admin/'.$path
		);
		Functions\when( 'add_query_arg' )->alias(
			static function ( $args, string $url = '' ) :string {
				if ( \is_array( $args ) ) {
					return $url.( \str_contains( $url, '?' ) ? '&' : '?' ).\http_build_query( $args );
				}
				return $url;
			}
		);
		Functions\when( 'esc_url_raw' )->returnArg();
		Functions\when( 'wp_die' )->justReturn( null );
	}
}

class AsyncQueueHarness {

	/**
	 * @var array<string,array<int,array{callback:mixed,priority:int,accepted_args:int}>>
	 */
	public array $actions = [];

	/**
	 * @var array<int,array{timestamp:int,hook:string,recurrence:string}>
	 */
	public array $scheduled = [];

	/**
	 * @var array<int,array{url:string,args:array}>
	 */
	public array $remotePosts = [];

	/**
	 * @var array|false
	 */
	public $remotePostResponse = [ 'response' => [ 'code' => 200 ] ];

	/**
	 * @var array<int,array{hook:string,args:array}>
	 */
	public array $didActions = [];

	/**
	 * @var array<string,int>
	 */
	private array $scheduleAttempts = [];

	public function addAction( string $hook, $callback, int $priority, int $acceptedArgs ) :void {
		$this->actions[ $hook ][] = [
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $acceptedArgs,
		];
	}

	public function addFilter( string $hook, $callback, int $priority, int $acceptedArgs ) :void {
		$this->addAction( $hook, $callback, $priority, $acceptedArgs );
	}

	public function doAction( string $hook, array $args ) :void {
		$this->didActions[] = [
			'hook' => $hook,
			'args' => $args,
		];
	}

	public function scheduleEvent( int $timestamp, string $hook, string $recurrence ) :void {
		$this->scheduleAttempts[ $hook ] = ( $this->scheduleAttempts[ $hook ] ?? 0 ) + 1;
		if ( $this->nextScheduled( $hook ) !== false ) {
			return;
		}
		$this->scheduled[] = [
			'timestamp'  => $timestamp,
			'hook'       => $hook,
			'recurrence' => $recurrence,
		];
	}

	public function nextScheduled( string $hook ) {
		foreach ( $this->scheduled as $event ) {
			if ( $event[ 'hook' ] === $hook ) {
				return $event[ 'timestamp' ];
			}
		}
		return false;
	}

	public function unscheduleEvent( int $timestamp, string $hook ) :void {
		$this->scheduled = \array_values( \array_filter(
			$this->scheduled,
			static fn( array $event ) :bool => !( $event[ 'hook' ] === $hook && $event[ 'timestamp' ] === $timestamp )
		) );
	}

	public function unscheduleHook( string $hook ) :void {
		$this->scheduled = \array_values( \array_filter(
			$this->scheduled,
			static fn( array $event ) :bool => $event[ 'hook' ] !== $hook
		) );
	}

	public function hasScheduledHook( string $hook ) :bool {
		return $this->nextScheduled( $hook ) !== false;
	}

	public function scheduledHookAttempts( string $hook ) :int {
		return $this->scheduleAttempts[ $hook ] ?? 0;
	}

	public function resetTransport() :void {
		$this->scheduled = [];
		$this->remotePosts = [];
		$this->scheduleAttempts = [];
	}
}

class LifecycleSqliteDb extends Db {

	private \PDO $pdo;

	/**
	 * @var string[]
	 */
	private array $queryLog = [];

	private int $now;

	public function __construct( int $now ) {
		$this->now = $now;
		$this->pdo = new \PDO( 'sqlite::memory:' );
		$this->pdo->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );
		$this->createTables();
	}

	public function insertScan( array $data ) :int {
		$data = \array_merge( [
			'scan'            => '',
			'status'          => '',
			'scope_type'      => 'full',
			'scope_key'       => '',
			'run_trigger'     => 'manual',
			'started_at'      => 0,
			'last_process_at' => 0,
			'ready_at'        => 0,
			'finished_at'     => 0,
			'meta'            => \base64_encode( '[]' ),
			'created_at'      => $this->now,
		], $data );
		$this->insertRow( 'scans', $data );
		return (int)$this->pdo->lastInsertId();
	}

	public function insertScanItem( array $data ) :int {
		$data = \array_merge( [
			'scan_ref'    => 0,
			'items'       => \base64_encode( '[]' ),
			'item_count'  => 0,
			'started_at'  => 0,
			'attempts'    => 0,
			'finished_at' => 0,
		], $data );
		$this->insertRow( 'scan_items', $data );
		return (int)$this->pdo->lastInsertId();
	}

	public function updateRowById( string $table, int $id, array $data ) :bool {
		if ( empty( $data ) ) {
			return true;
		}
		$sets = [];
		$params = [ ':id' => $id ];
		foreach ( $data as $column => $value ) {
			$param = ':'.$column;
			$sets[] = sprintf( '`%s`=%s', $column, $param );
			$params[ $param ] = $value;
		}
		$sql = sprintf( 'UPDATE `%s` SET %s WHERE `id`=:id', $table, \implode( ',', $sets ) );
		$this->recordQuery( $sql );
		$stmt = $this->pdo->prepare( $sql );
		return $stmt->execute( $params );
	}

	public function deleteRows( string $table, array $wheres, array $params ) :bool {
		$sql = sprintf( 'DELETE FROM `%s` %s', $table, empty( $wheres ) ? '' : 'WHERE '.\implode( ' AND ', $wheres ) );
		$this->recordQuery( $sql );
		$stmt = $this->pdo->prepare( $sql );
		return $stmt->execute( $params );
	}

	public function fetchRows( string $table, array $wheres = [], array $params = [], string $orderBy = '', int $limit = 0, array $columns = [] ) :array {
		$select = empty( $columns ) ? '*' : \implode( ', ', \array_map(
			static fn( string $column ) :string => sprintf( '`%s`', $column ),
			$columns
		) );
		$sql = sprintf( 'SELECT %s FROM `%s` %s', $select, $table, empty( $wheres ) ? '' : 'WHERE '.\implode( ' AND ', $wheres ) );
		if ( $orderBy !== '' ) {
			$sql .= ' ORDER BY '.$orderBy;
		}
		if ( $limit > 0 ) {
			$sql .= ' LIMIT '.$limit;
		}
		$this->recordQuery( $sql );
		$stmt = $this->pdo->prepare( $sql );
		$stmt->execute( $params );
		return $stmt->fetchAll( \PDO::FETCH_ASSOC ) ?: [];
	}

	public function countRows( string $table, array $wheres = [], array $params = [] ) :int {
		$sql = sprintf( 'SELECT COUNT(*) FROM `%s` %s', $table, empty( $wheres ) ? '' : 'WHERE '.\implode( ' AND ', $wheres ) );
		$this->recordQuery( $sql );
		$stmt = $this->pdo->prepare( $sql );
		$stmt->execute( $params );
		return (int)$stmt->fetchColumn();
	}

	public function distinctColumn( string $table, string $column, array $wheres = [], array $params = [] ) :array {
		$sql = sprintf( 'SELECT DISTINCT `%s` FROM `%s` %s', $column, $table, empty( $wheres ) ? '' : 'WHERE '.\implode( ' AND ', $wheres ) );
		$this->recordQuery( $sql );
		$stmt = $this->pdo->prepare( $sql );
		$stmt->execute( $params );
		return \array_map( static fn( array $row ) => $row[ $column ], $stmt->fetchAll( \PDO::FETCH_ASSOC ) ?: [] );
	}

	public function scanRow( int $scanID ) :array {
		return $this->fetchRows( 'scans', [ '`id`=:id' ], [ ':id' => $scanID ], '', 1 )[ 0 ] ?? [];
	}

	public function scanItemRow( int $itemID ) :array {
		return $this->fetchRows( 'scan_items', [ '`id`=:id' ], [ ':id' => $itemID ], '', 1 )[ 0 ] ?? [];
	}

	public function scanRows() :array {
		return $this->fetchRows( 'scans', [], [], '`id` ASC' );
	}

	public function countScanItems( int $scanID ) :int {
		return $this->countRows( 'scan_items', [ '`scan_ref`=:scan_ref' ], [ ':scan_ref' => $scanID ] );
	}

	public function getVar( $sql ) {
		$this->recordQuery( (string)$sql );
		if ( \stripos( (string)$sql, 'LAST_INSERT_ID()' ) !== false ) {
			return (int)$this->pdo->lastInsertId();
		}
		$stmt = $this->pdo->query( (string)$sql );
		return $stmt === false ? null : $stmt->fetchColumn();
	}

	public function selectRow( string $query, $format = null ) {
		unset( $format );
		$this->recordQuery( $query );
		$stmt = $this->pdo->query( $query );
		if ( $stmt === false ) {
			return null;
		}
		$row = $stmt->fetch( \PDO::FETCH_ASSOC );
		return \is_array( $row ) ? $row : null;
	}

	/**
	 * @return array<int,array<string,string|null>>
	 */
	public function selectCustom( $query, $format = null ) :array {
		unset( $format );
		$this->recordQuery( (string)$query );
		$stmt = $this->pdo->query( (string)$query );
		$rows = $stmt === false ? [] : ( $stmt->fetchAll( \PDO::FETCH_ASSOC ) ?: [] );
		// wpdb returns database scalar values as strings in ARRAY_A-style results.
		return \array_map(
			static fn( array $row ) :array => \array_map(
				static fn( $value ) => $value === null ? null : (string)$value,
				$row
			),
			$rows
		);
	}

	public function doSql( string $sqlQuery ) {
		$this->recordQuery( $sqlQuery );
		$result = $this->pdo->exec( $sqlQuery );
		return $result === false ? false : $result;
	}

	public function resetQueryLog() :void {
		$this->queryLog = [];
	}

	public function queryLog() :array {
		return $this->queryLog;
	}

	private function createTables() :void {
		$this->pdo->exec( 'CREATE TABLE `scans` (
			`id` INTEGER PRIMARY KEY AUTOINCREMENT,
			`scan` TEXT NOT NULL,
			`status` TEXT NOT NULL,
			`scope_type` TEXT NOT NULL,
			`scope_key` TEXT NOT NULL,
			`run_trigger` TEXT NOT NULL,
			`started_at` INTEGER NOT NULL DEFAULT 0,
			`last_process_at` INTEGER NOT NULL DEFAULT 0,
			`ready_at` INTEGER NOT NULL DEFAULT 0,
			`finished_at` INTEGER NOT NULL DEFAULT 0,
			`meta` TEXT NOT NULL DEFAULT "",
			`created_at` INTEGER NOT NULL DEFAULT 0
		)' );
		$this->pdo->exec( 'CREATE TABLE `scan_items` (
			`id` INTEGER PRIMARY KEY AUTOINCREMENT,
			`scan_ref` INTEGER NOT NULL,
			`items` TEXT NOT NULL DEFAULT "",
			`item_count` INTEGER NOT NULL DEFAULT 0,
			`started_at` INTEGER NOT NULL DEFAULT 0,
			`attempts` INTEGER NOT NULL DEFAULT 0,
			`finished_at` INTEGER NOT NULL DEFAULT 0
		)' );
		$this->pdo->exec( 'CREATE TABLE `scan_results` (
			`id` INTEGER PRIMARY KEY AUTOINCREMENT,
			`scan_ref` INTEGER NOT NULL DEFAULT 0,
			`resultitem_ref` INTEGER NOT NULL DEFAULT 0,
			`created_at` INTEGER NOT NULL DEFAULT 0
		)' );
		$this->pdo->exec( 'CREATE TABLE `scan_result_items` (
			`id` INTEGER PRIMARY KEY AUTOINCREMENT,
			`scan` TEXT NOT NULL DEFAULT "",
			`item_type` TEXT NOT NULL DEFAULT "",
			`item_id` TEXT NOT NULL DEFAULT "",
			`asset_type` TEXT NOT NULL DEFAULT "",
			`asset_key` TEXT NOT NULL DEFAULT "",
			`resolved_at` INTEGER NOT NULL DEFAULT 0,
			`item_repaired_at` INTEGER NOT NULL DEFAULT 0,
			`item_deleted_at` INTEGER NOT NULL DEFAULT 0
		)' );
	}

	private function insertRow( string $table, array $data ) :void {
		$columns = \array_keys( $data );
		$params = \array_map( static fn( string $column ) :string => ':'.$column, $columns );
		$sql = sprintf(
			'INSERT INTO `%s` (`%s`) VALUES (%s)',
			$table,
			\implode( '`,`', $columns ),
			\implode( ',', $params )
		);
		$this->recordQuery( $sql );
		$stmt = $this->pdo->prepare( $sql );
		$stmt->execute( \array_combine( $params, \array_values( $data ) ) ?: [] );
	}

	private function recordQuery( string $sql ) :void {
		$this->queryLog[] = $sql;
	}
}

class LifecycleScansDb {

	public array $rawInserts = [];

	private LifecycleSqliteDb $db;
	private bool $failNextUpdate = false;

	public function __construct( LifecycleSqliteDb $db ) {
		$this->db = $db;
	}

	public function getTable() :string {
		return 'scans';
	}

	public function getRecord() :ScansDB\Record {
		return new ScansDB\Record();
	}

	public function getQueryInserter() :object {
		return new class( $this->db, $this->rawInserts ) {
			private LifecycleSqliteDb $db;
			private array $rawInserts;

			public function __construct( LifecycleSqliteDb $db, array &$rawInserts ) {
				$this->db = $db;
				$this->rawInserts =& $rawInserts;
			}

			public function insert( ScansDB\Record $record ) :bool {
				$raw = $record->getRawData();
				$this->rawInserts[] = $raw;
				$this->db->insertScan( $raw );
				return true;
			}
		};
	}

	public function getQuerySelector() :LifecycleScansSelector {
		return new LifecycleScansSelector( $this->db );
	}

	public function getQueryUpdater() :object {
		return new class( $this ) {
			private LifecycleScansDb $db;

			public function __construct( LifecycleScansDb $db ) {
				$this->db = $db;
			}

			public function updateById( int $id, array $data ) :bool {
				return $this->db->updateById( $id, $data );
			}
		};
	}

	public function failNextUpdate() :void {
		$this->failNextUpdate = true;
	}

	public function updateById( int $id, array $data ) :bool {
		if ( $this->failNextUpdate ) {
			$this->failNextUpdate = false;
			return false;
		}
		return $this->db->updateRowById( 'scans', $id, $data );
	}
}

class LifecycleScanItemsDb {

	private LifecycleSqliteDb $db;

	public function __construct( LifecycleSqliteDb $db ) {
		$this->db = $db;
	}

	public function getTable() :string {
		return 'scan_items';
	}

	public function getRecord() :ScanItemsDB\Record {
		return new ScanItemsDB\Record();
	}

	public function getQueryInserter() :object {
		return new class( $this->db ) {
			private LifecycleSqliteDb $db;

			public function __construct( LifecycleSqliteDb $db ) {
				$this->db = $db;
			}

			public function insert( ScanItemsDB\Record $record ) :bool {
				$this->db->insertScanItem( $record->getRawData() );
				return true;
			}
		};
	}

	public function getQuerySelector() :LifecycleScanItemsSelector {
		return new LifecycleScanItemsSelector( $this->db );
	}

	public function getQueryUpdater() :object {
		return new class( $this->db ) {
			private LifecycleSqliteDb $db;

			public function __construct( LifecycleSqliteDb $db ) {
				$this->db = $db;
			}

			public function updateById( int $id, array $data ) :bool {
				return $this->db->updateRowById( 'scan_items', $id, $data );
			}
		};
	}

	public function getQueryDeleter() :LifecycleScanItemsDeleter {
		return new LifecycleScanItemsDeleter( $this->db );
	}

	public function tableDelete() :bool {
		return $this->db->deleteRows( 'scan_items', [], [] );
	}
}

class LifecycleScansSelector {

	use LifecycleWhereBuilder;

	/**
	 * @var string[]
	 */
	private array $orderBy = [];

	private int $limit = 0;

	private array $columnsToSelect = [];

	private LifecycleSqliteDb $db;

	public function __construct( LifecycleSqliteDb $db ) {
		$this->db = $db;
		$this->reset();
	}

	public function reset() :self {
		$this->resetWhereBuilder();
		$this->orderBy = [];
		$this->limit = 0;
		$this->columnsToSelect = [];
		return $this;
	}

	public function filterByScan( string $scan ) :self {
		return $this->addWhereEquals( 'scan', $scan );
	}

	public function filterByScope( string $scopeType, string $scopeKey = '' ) :self {
		return $this->addWhereEquals( 'scope_type', $scopeType )->addWhereEquals( 'scope_key', $scopeKey );
	}

	public function filterByStatus( string $status ) :self {
		return $this->addWhereEquals( 'status', $status );
	}

	public function filterByIDs( array $ids ) :self {
		return $this->addWhereIn( 'id', \array_map( '\intval', $ids ) );
	}

	public function filterByNotFinished() :self {
		return $this->addWhereEquals( 'finished_at', 0 );
	}

	public function filterByReady() :self {
		return $this->addWhereNewerThan( 0, 'ready_at' );
	}

	public function setOrderBy( string $column, string $direction = 'DESC', bool $overwrite = false ) :self {
		if ( $overwrite ) {
			$this->orderBy = [];
		}
		$this->orderBy[] = sprintf( '`%s` %s', $column, \strtoupper( $direction ) === 'ASC' ? 'ASC' : 'DESC' );
		return $this;
	}

	public function setLimit( int $limit ) :self {
		$this->limit = $limit;
		return $this;
	}

	public function setColumnsToSelect( array $columns ) :self {
		$this->columnsToSelect = \array_values( \array_filter(
			\array_map( '\strval', $columns ),
			static fn( string $column ) :bool => $column !== ''
		) );
		return $this;
	}

	public function count() :int {
		$count = $this->db->countRows( 'scans', $this->wheres, $this->params );
		$this->reset();
		return $count;
	}

	public function byId( int $id ) :?ScansDB\Record {
		$this->reset()->addWhereEquals( 'id', $id )->setLimit( 1 );
		$rows = $this->db->fetchRows( 'scans', $this->wheres, $this->params, '', $this->limit );
		$this->reset();
		return empty( $rows ) ? null : $this->recordFromRow( $rows[ 0 ] );
	}

	public function first() :?ScansDB\Record {
		$this->setLimit( 1 );
		$rows = $this->queryRows();
		return empty( $rows ) ? null : $this->recordFromRow( $rows[ 0 ] );
	}

	/**
	 * @return ScansDB\Record[]
	 */
	public function queryWithResult() :array {
		return \array_map( [ $this, 'recordFromRow' ], $this->queryRows() );
	}

	public function getDistinctForColumn( string $column ) :array {
		$values = $this->db->distinctColumn( 'scans', $column, $this->wheres, $this->params );
		$this->reset();
		return $values;
	}

	private function queryRows() :array {
		$rows = $this->db->fetchRows( 'scans', $this->wheres, $this->params, \implode( ', ', $this->orderBy ), $this->limit, $this->columnsToSelect );
		$this->reset();
		return $rows;
	}

	private function recordFromRow( array $row ) :ScansDB\Record {
		return new ScansDB\Record( $row );
	}
}

class LifecycleScanItemsSelector {

	use LifecycleWhereBuilder;

	private LifecycleSqliteDb $db;

	public function __construct( LifecycleSqliteDb $db ) {
		$this->db = $db;
		$this->reset();
	}

	public function reset() :self {
		$this->resetWhereBuilder();
		return $this;
	}

	public function filterByScan( int $scanID ) :self {
		return $this->addWhereEquals( 'scan_ref', $scanID );
	}

	public function filterByNotFinished() :self {
		return $this->addWhereEquals( 'finished_at', 0 );
	}

	public function filterByFinished() :self {
		return $this->addWhereNewerThan( 0, 'finished_at' );
	}

	public function filterByStarted() :self {
		return $this->addWhereNewerThan( 0, 'started_at' );
	}

	public function filterByNotStarted() :self {
		return $this->addWhereEquals( 'started_at', 0 );
	}

	public function count() :int {
		$count = $this->db->countRows( 'scan_items', $this->wheres, $this->params );
		$this->reset();
		return $count;
	}

	public function countAllForEachScan() :array {
		return $this->countsFromRows( $this->db->selectCustom(
			'SELECT `scan_ref`, COUNT(*) AS `count` FROM `scan_items` GROUP BY `scan_ref`'
		) );
	}

	public function countUnfinishedForEachScan() :array {
		return $this->countsFromRows( $this->db->selectCustom(
			'SELECT `scan_ref`, COUNT(*) AS `count` FROM `scan_items` WHERE `finished_at`=0 GROUP BY `scan_ref`'
		) );
	}

	public function countProgressForEachScan() :array {
		$rows = $this->db->selectCustom( 'SELECT `scan_ref`, SUM(CASE WHEN `item_count`>0 THEN `item_count` ELSE 1 END) AS `count_all`, SUM(CASE WHEN `finished_at`=0 THEN CASE WHEN `item_count`>0 THEN `item_count` ELSE 1 END ELSE 0 END) AS `count_unfinished` FROM `scan_items` GROUP BY `scan_ref`' );
		$counts = [];
		foreach ( $rows as $row ) {
			$counts[ (int)$row[ 'scan_ref' ] ] = [
				'total'      => (int)$row[ 'count_all' ],
				'unfinished' => (int)$row[ 'count_unfinished' ],
			];
		}
		return $counts;
	}

	private function countsFromRows( array $rows ) :array {
		$counts = [];
		foreach ( $rows as $row ) {
			$counts[ (int)$row[ 'scan_ref' ] ] = (int)$row[ 'count' ];
		}
		return $counts;
	}
}

class LifecycleScanItemsDeleter {

	use LifecycleWhereBuilder;

	private LifecycleSqliteDb $db;

	public function __construct( LifecycleSqliteDb $db ) {
		$this->db = $db;
		$this->resetWhereBuilder();
	}

	public function filterByScan( int $scanID ) :self {
		return $this->addWhereEquals( 'scan_ref', $scanID );
	}

	public function filterByNotFinished() :self {
		return $this->addWhereEquals( 'finished_at', 0 );
	}

	public function filterByFinished() :self {
		return $this->addWhereNewerThan( 0, 'finished_at' );
	}

	public function deleteById( int $id ) :bool {
		return $this->db->deleteRows( 'scan_items', [ '`id`=:id' ], [ ':id' => $id ] );
	}

	public function query() :bool {
		$result = $this->db->deleteRows( 'scan_items', $this->wheres, $this->params );
		$this->resetWhereBuilder();
		return $result;
	}
}

trait LifecycleWhereBuilder {

	/**
	 * @var string[]
	 */
	protected array $wheres = [];

	/**
	 * @var array<string,mixed>
	 */
	protected array $params = [];

	private int $paramCounter = 0;

	public function addWhereEquals( string $column, $value ) :self {
		$param = $this->nextParam();
		$this->wheres[] = sprintf( '`%s`=%s', $column, $param );
		$this->params[ $param ] = $value;
		return $this;
	}

	public function addWhereIn( string $column, array $values ) :self {
		if ( empty( $values ) ) {
			$this->wheres[] = '1=0';
			return $this;
		}
		$params = [];
		foreach ( \array_values( $values ) as $value ) {
			$param = $this->nextParam();
			$params[] = $param;
			$this->params[ $param ] = $value;
		}
		$this->wheres[] = sprintf( '`%s` IN (%s)', $column, \implode( ',', $params ) );
		return $this;
	}

	public function addWhereOlderThan( int $timestamp, string $column = 'created_at' ) :self {
		$param = $this->nextParam();
		$this->wheres[] = sprintf( '`%s`<%s', $column, $param );
		$this->params[ $param ] = $timestamp;
		return $this;
	}

	public function addWhereNewerThan( int $timestamp, string $column ) :self {
		$param = $this->nextParam();
		$this->wheres[] = sprintf( '`%s`>%s', $column, $param );
		$this->params[ $param ] = $timestamp;
		return $this;
	}

	protected function resetWhereBuilder() :void {
		$this->wheres = [];
		$this->params = [];
		$this->paramCounter = 0;
	}

	private function nextParam() :string {
		return ':p'.( ++$this->paramCounter );
	}
}

class LifecycleEmptyDbHandler {

	private string $table;

	private ?LifecycleSqliteDb $db;
	private bool $failNextInsert = false;
	private int $consumedInsertFailures = 0;

	public function __construct( string $table, ?LifecycleSqliteDb $db = null ) {
		$this->table = $table;
		$this->db = $db;
	}

	public function getTable() :string {
		return $this->table;
	}

	public function failNextInsert() :void {
		$this->failNextInsert = true;
	}

	public function consumeInsertFailure() :bool {
		$failed = $this->failNextInsert;
		$this->failNextInsert = false;
		if ( $failed ) {
			$this->consumedInsertFailures++;
		}
		return $failed;
	}

	public function countConsumedInsertFailures() :int {
		return $this->consumedInsertFailures;
	}

	public function getQuerySelector() :object {
		return new class {
			public function filterByScan( $value ) :self {
				unset( $value );
				return $this;
			}

			public function filterByResultItem( int $value ) :self {
				unset( $value );
				return $this;
			}

			public function filterByItemType( string $value ) :self {
				unset( $value );
				return $this;
			}

			public function filterByItemID( string $value ) :self {
				unset( $value );
				return $this;
			}

			public function filterByUnresolved() :self {
				return $this;
			}

			public function first() {
				return null;
			}

			public function byId( int $id ) :object {
				return (object)[ 'id' => $id, 'meta' => [] ];
			}

			public function count() :int {
				return 0;
			}

			public function getDistinctForColumn( string $column ) :array {
				unset( $column );
				return [];
			}

			public function queryWithResult() :array {
				return [];
			}
		};
	}

	public function getQueryInserter() :object {
		return new class( $this ) {
			private LifecycleEmptyDbHandler $db;

			public function __construct( LifecycleEmptyDbHandler $db ) {
				$this->db = $db;
			}

			public function insert( $record ) :bool {
				unset( $record );
				return !$this->db->consumeInsertFailure();
			}

			public function setInsertData( array $data ) :self {
				unset( $data );
				return $this;
			}

			public function query() :bool {
				return true;
			}
		};
	}

	public function getQueryUpdater() :object {
		return new class {
			public function updateRecord( $record, array $data ) :bool {
				unset( $record, $data );
				return true;
			}
		};
	}

	public function getQueryDeleter() :object {
		return new class {
			public function filterByResultItemRef( int $id ) :self {
				unset( $id );
				return $this;
			}

			public function filterByResultItems( array $ids ) :self {
				unset( $ids );
				return $this;
			}

			public function query() :bool {
				return true;
			}
		};
	}

	public function tableDelete() :bool {
		return $this->db === null ? true : $this->db->deleteRows( $this->table, [], [] );
	}
}

class LifecycleScansComponent {

	/**
	 * @var array<string,LifecycleScanController>
	 */
	private array $controllers = [];

	/**
	 * @param array<string,string[]> $itemsByScan
	 */
	public function __construct( array $itemsByScan ) {
		foreach ( [ 'afs', 'apc', 'wpv' ] as $slug ) {
			$this->controllers[ $slug ] = new LifecycleScanController( $slug, $itemsByScan[ $slug ] ?? [] );
		}
	}

	public function failBuildFor( string $slug ) :void {
		if ( isset( $this->controllers[ $slug ] ) ) {
			$this->controllers[ $slug ]->failBuild();
		}
	}

	public function getScanCon( string $slug ) :?LifecycleScanController {
		return $this->controllers[ $slug ] ?? null;
	}

	public function AFS() :LifecycleScanController {
		return $this->controllers[ 'afs' ];
	}

	public function getScanSlugs() :array {
		return \array_keys( $this->controllers );
	}

	public function getAllScanCons() :array {
		return \array_values( $this->controllers );
	}
}

class LifecycleScanController extends Base {

	/**
	 * @param string[] $items
	 */
	private string $slug;
	private array $items;
	private bool $failBuild = false;

	public function __construct( string $slug, array $items ) {
		$this->slug = $slug;
		$this->items = $items;
	}

	public function getSlug() :string {
		return $this->slug;
	}

	public function isReady() :bool {
		return true;
	}

	public function isEnabled() :bool {
		return true;
	}

	public function isRestricted() :bool {
		return false;
	}

	public function getQueueGroupSize() :int {
		return 1;
	}

	public function getStrings() :array {
		return [
			'name'     => \strtoupper( $this->slug ),
			'subtitle' => '',
		];
	}

	protected function newItemActionHandler() {
		return null;
	}

	public function buildScanAction( ?BaseScanActionVO $scanAction = null ) {
		if ( $this->failBuild ) {
			throw new \RuntimeException( 'builder unavailable' );
		}
		$scanAction ??= $this->newScanActionVO();
		$scanAction->items = $this->items;
		$scanAction->usleep = 0;
		return $scanAction;
	}

	public function failBuild() :void {
		$this->failBuild = true;
	}

	public function buildScanResult( array $rawResult ) :ResultItemsDB\Record {
		$slug = (string)( $rawResult[ 'slug' ] ?? '' );
		$record = new ResultItemsDB\Record();
		$record->scan = $this->slug;
		$record->item_type = 'p';
		$record->item_id = $slug;
		$record->asset_type = 'plugin';
		$record->asset_key = $slug;
		$record->auto_filtered_at = 0;
		$record->last_seen_at = 1700000000;
		$record->resolved_at = 0;
		$record->resolution_reason = '';
		unset( $rawResult[ 'slug' ] );
		$record->meta = $rawResult;
		return $record;
	}
}

class LifecycleQueueComponent extends QueueController {

	public QueueBuilder $builder;

	public QueueProcessor $processor;

	public ?QueueWatchdog $watchdog = null;

	public LifecycleScansComponent $scansComponent;

	public function getQueueBuilder() :QueueBuilder {
		return $this->builder;
	}

	public function getQueueProcessor() :QueueProcessor {
		return $this->processor;
	}

	public function getQueueWatchdog() :QueueWatchdog {
		return $this->watchdog ??= new QueueWatchdog();
	}
}

class LifecycleActionRouter {

	public array $renderData = [];

	public function render( string $unused, array $data ) :string {
		unset( $unused );
		$this->renderData = $data;
		return '';
	}
}

class LifecycleEventsComponent {

	public array $events = [];

	public function fireEvent( string $event, array $meta = [] ) :void {
		$this->events[] = [
			'event' => $event,
			'meta'  => $meta,
		];
	}
}

class LifecycleOpts {

	private array $values = [
		'is_scan_cron' => false,
	];

	public function optGet( string $key ) {
		return $this->values[ $key ] ?? false;
	}

	public function optSet( string $key, $value ) :self {
		$this->values[ $key ] = $value;
		return $this;
	}

	public function store() :self {
		return $this;
	}
}

class LifecycleOptsLookup {

	public function isPluginEnabled() :bool {
		return true;
	}

	public function isScanAutoFilterResults() :bool {
		return false;
	}
}

class LifecycleFileLocker {

	public function purge() :void {
	}
}

class LifecycleRequest extends Request {

	private int $timestamp;

	public function __construct( int $timestamp ) {
		$this->timestamp = $timestamp;
	}

	public function ts( bool $update = true ) :int {
		unset( $update );
		return $this->timestamp;
	}

	public function carbon( $setTimezone = false, bool $userLocale = true ) :Carbon {
		unset( $setTimezone, $userLocale );
		return Carbon::createFromTimestampUTC( $this->timestamp );
	}
}

class LifecycleGeneral extends General {
	public bool $wpCli = false;

	public function isWpCli() :bool {
		return $this->wpCli;
	}
}

class LifecycleAfsFs extends Fs {

	public function exists( $path ) :?bool {
		return \file_exists( $path );
	}

	public function getModifiedTime( string $path ) :int {
		return (int)\filemtime( $path );
	}

	public function getFileContent( $path, $uncompress = false ) {
		$content = \file_get_contents( $path );
		if ( \is_string( $content ) && $uncompress ) {
			$inflated = \gzinflate( $content );
			$content = \is_string( $inflated ) ? $inflated : null;
		}
		return $content;
	}
}

class LifecyclePlugins extends Plugins {

	public function getPlugin( $file ) :array {
		unset( $file );
		return [];
	}

	public function getPluginAsVo( string $file, bool $reload = false ) :?WpPluginVo {
		unset( $reload );
		return \strpos( $file, '/' ) === false ? null : new LifecyclePluginVo( $file );
	}
}

class LifecyclePluginVo extends WpPluginVo {

	public string $file;

	public function __construct( string $file ) {
		$this->file = $file;
	}

	public function __get( string $key ) {
		return $key === 'slug'
			? \dirname( $this->file )
			: ( $key === 'Version' ? '1.0.0' : parent::__get( $key ) );
	}

	public function isWpOrg() :bool {
		return true;
	}
}
