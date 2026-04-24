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
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\QueueProcessor;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseScanActionVO;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\{
	Db,
	General,
	Request
};

class ScanQueueLifecycleHarness {

	public AsyncQueueHarness $async;

	public LifecycleSqliteDb $sql;

	public LifecycleScansDb $scansDb;

	public LifecycleScanItemsDb $scanItemsDb;

	private LifecycleQueueComponent $queueComponent;

	/**
	 * @param array<string,string[]> $itemsByScan
	 */
	public function __construct(
		private int $now = 1700000000,
		private array $itemsByScan = [
			'afs' => [ 'afs-a', 'afs-b' ],
			'apc' => [ 'apc-a' ],
			'wpv' => [ 'wpv-a' ],
		]
	) {
		$this->async = new AsyncQueueHarness();
		$this->sql = new LifecycleSqliteDb( $this->now );
		$this->scansDb = new LifecycleScansDb( $this->sql );
		$this->scanItemsDb = new LifecycleScanItemsDb( $this->sql );
		$this->queueComponent = new LifecycleQueueComponent();
	}

	public function install() :self {
		$this->installWordPressFunctions();
		ServicesState::installItems( [
			'service_request'   => new LifecycleRequest( $this->now ),
			'service_wpdb'      => $this->sql,
			'service_wpgeneral' => new LifecycleGeneral(),
		] );
		$this->installController();
		$this->queueComponent->builder = new QueueBuilder();
		$this->queueComponent->processor = new QueueProcessor();
		return $this;
	}

	public function builder() :QueueBuilder {
		return $this->queueComponent->builder;
	}

	public function processor() :QueueProcessor {
		return $this->queueComponent->processor;
	}

	public function insertScan( array $data ) :int {
		return $this->sql->insertScan( $data );
	}

	public function insertScanItem( int $scanID, array $items, int $startedAt = 0, int $finishedAt = 0 ) :int {
		return $this->sql->insertScanItem( [
			'scan_ref'    => $scanID,
			'items'       => \base64_encode( \json_encode( $items ) ?: '[]' ),
			'started_at'  => $startedAt,
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
			'scan_results'          => new LifecycleEmptyDbHandler( 'scan_results' ),
			'scan_result_items'     => new LifecycleEmptyDbHandler( 'scan_result_items' ),
			'scan_result_item_meta' => new LifecycleEmptyDbHandler( 'scan_result_item_meta' ),
		];
		$controller->comps = (object)[
			'scans'        => new LifecycleScansComponent( $this->itemsByScan ),
			'scans_queue'  => $this->queueComponent,
			'events'       => new LifecycleEventsComponent(),
			'opts_lookup'  => new LifecycleOptsLookup(),
		];
		$controller->opts = new LifecycleOpts();
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
		Functions\when( 'absint' )->alias(
			static fn( $value ) :int => \abs( (int)$value )
		);
		Functions\when( 'wp_convert_hr_to_bytes' )->alias(
			static fn( $value ) :int => 134217728
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
			static function ( string $url, array $args = [] ) use ( $async ) :array {
				$async->remotePosts[] = [
					'url'  => $url,
					'args' => $args,
				];
				return [ 'response' => [ 'code' => 200 ] ];
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
	 * @var array<int,array{hook:string,args:array}>
	 */
	public array $didActions = [];

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

	public function resetTransport() :void {
		$this->scheduled = [];
		$this->remotePosts = [];
	}
}

class LifecycleSqliteDb extends Db {

	private \PDO $pdo;

	/**
	 * @var string[]
	 */
	private array $queryLog = [];

	public function __construct( private int $now ) {
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
			'started_at'  => 0,
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

	public function fetchRows( string $table, array $wheres = [], array $params = [], string $orderBy = '', int $limit = 0 ) :array {
		$sql = sprintf( 'SELECT * FROM `%s` %s', $table, empty( $wheres ) ? '' : 'WHERE '.\implode( ' AND ', $wheres ) );
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

	public function selectCustom( $query, $format = \FernleafSystems\Wordpress\Services\Core\ARRAY_A ) {
		unset( $format );
		$this->recordQuery( (string)$query );
		$stmt = $this->pdo->query( (string)$query );
		return $stmt === false ? [] : ( $stmt->fetchAll( \PDO::FETCH_ASSOC ) ?: [] );
	}

	public function doSql( string $sqlQuery ) {
		$this->recordQuery( $sqlQuery );
		return $this->pdo->exec( $sqlQuery ) !== false;
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
			`started_at` INTEGER NOT NULL DEFAULT 0,
			`finished_at` INTEGER NOT NULL DEFAULT 0
		)' );
		$this->pdo->exec( 'CREATE TABLE `scan_results` (`id` INTEGER PRIMARY KEY AUTOINCREMENT, `scan_ref` INTEGER, `resultitem_ref` INTEGER)' );
		$this->pdo->exec( 'CREATE TABLE `scan_result_items` (`id` INTEGER PRIMARY KEY AUTOINCREMENT, `scan` TEXT, `resolved_at` INTEGER DEFAULT 0, `resolution_reason` TEXT DEFAULT "")' );
		$this->pdo->exec( 'CREATE TABLE `scan_result_item_meta` (`id` INTEGER PRIMARY KEY AUTOINCREMENT, `ri_ref` INTEGER, `meta_key` TEXT, `meta_value` TEXT)' );
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

	public function __construct( private LifecycleSqliteDb $db ) {
	}

	public function getTable() :string {
		return 'scans';
	}

	public function getRecord() :ScansDB\Record {
		return new ScansDB\Record();
	}

	public function getQueryInserter() :object {
		return new class( $this->db, $this->rawInserts ) {
			public function __construct( private LifecycleSqliteDb $db, private array &$rawInserts ) {
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
		return new class( $this->db ) {
			public function __construct( private LifecycleSqliteDb $db ) {
			}

			public function updateById( int $id, array $data ) :bool {
				return $this->db->updateRowById( 'scans', $id, $data );
			}
		};
	}
}

class LifecycleScanItemsDb {

	public function __construct( private LifecycleSqliteDb $db ) {
	}

	public function getTable() :string {
		return 'scan_items';
	}

	public function getRecord() :ScanItemsDB\Record {
		return new ScanItemsDB\Record();
	}

	public function getQueryInserter() :object {
		return new class( $this->db ) {
			public function __construct( private LifecycleSqliteDb $db ) {
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
			public function __construct( private LifecycleSqliteDb $db ) {
			}

			public function updateById( int $id, array $data ) :bool {
				return $this->db->updateRowById( 'scan_items', $id, $data );
			}
		};
	}

	public function getQueryDeleter() :LifecycleScanItemsDeleter {
		return new LifecycleScanItemsDeleter( $this->db );
	}
}

class LifecycleScansSelector {

	use LifecycleWhereBuilder;

	private string $orderBy = '';

	private int $limit = 0;

	public function __construct( private LifecycleSqliteDb $db ) {
		$this->reset();
	}

	public function reset() :self {
		$this->resetWhereBuilder();
		$this->orderBy = '';
		$this->limit = 0;
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

	public function filterByNotFinished() :self {
		return $this->addWhereEquals( 'finished_at', 0 );
	}

	public function filterByReady() :self {
		return $this->addWhereNewerThan( 0, 'ready_at' );
	}

	public function setOrderBy( string $column, string $direction = 'DESC', bool $overwrite = false ) :self {
		unset( $overwrite );
		$this->orderBy = sprintf( '`%s` %s', $column, \strtoupper( $direction ) === 'ASC' ? 'ASC' : 'DESC' );
		return $this;
	}

	public function setLimit( int $limit ) :self {
		$this->limit = $limit;
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
		$rows = $this->db->fetchRows( 'scans', $this->wheres, $this->params, $this->orderBy, $this->limit );
		$this->reset();
		return $rows;
	}

	private function recordFromRow( array $row ) :ScansDB\Record {
		return new ScansDB\Record( $row );
	}
}

class LifecycleScanItemsSelector {

	use LifecycleWhereBuilder;

	public function __construct( private LifecycleSqliteDb $db ) {
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
		return [];
	}

	public function countUnfinishedForEachScan() :array {
		return [];
	}
}

class LifecycleScanItemsDeleter {

	use LifecycleWhereBuilder;

	public function __construct( private LifecycleSqliteDb $db ) {
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

	public function __construct( private string $table ) {
	}

	public function getTable() :string {
		return $this->table;
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
		return new class {
			public function insert( $record ) :bool {
				unset( $record );
				return true;
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

			public function query() :bool {
				return true;
			}
		};
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

	public function getScanCon( string $slug ) :?LifecycleScanController {
		return $this->controllers[ $slug ] ?? null;
	}

	public function getScanSlugs() :array {
		return \array_keys( $this->controllers );
	}
}

class LifecycleScanController extends Base {

	/**
	 * @param string[] $items
	 */
	public function __construct( private string $slug, private array $items ) {
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
		$scanAction ??= $this->newScanActionVO();
		$scanAction->items = $this->items;
		$scanAction->usleep = 0;
		return $scanAction;
	}

	public function buildScanResult( array $rawResult ) :ResultItemsDB\Record {
		unset( $rawResult );
		return new ResultItemsDB\Record();
	}
}

class LifecycleQueueComponent {

	public QueueBuilder $builder;

	public QueueProcessor $processor;

	public function getQueueBuilder() :QueueBuilder {
		return $this->builder;
	}

	public function getQueueProcessor() :QueueProcessor {
		return $this->processor;
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
}

class LifecycleRequest extends Request {

	public function __construct( private int $timestamp ) {
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

	public function isWpCli() :bool {
		return false;
	}
}
