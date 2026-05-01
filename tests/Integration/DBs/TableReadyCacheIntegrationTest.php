<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\DBs;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Handler;
use FernleafSystems\Wordpress\Plugin\Core\Databases\Common\TableReadyCache;
use FernleafSystems\Wordpress\Plugin\Core\Databases\Common\TableSchema;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Database\DbCon;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class TableReadyCacheIntegrationTest extends ShieldIntegrationTestCase {

	private const REPRESENTATIVE_DB_KEYS = [ 'events', 'ips', 'rules' ];

	private array $optionSnapshot = [];

	/**
	 * @var array<int,array{family:string,snippet:string}>
	 */
	private array $schemaProbeQueries = [];

	private ?\Closure $queryCaptureHook = null;

	public function set_up() {
		parent::set_up();

		$con = $this->requireController();
		$this->optionSnapshot = [
			'activated_at'       => $con->opts->optGet( 'activated_at' ),
			'transient_tracking' => $con->opts->optGet( 'transient_tracking' ),
		];

		$this->makeTableReadyCacheEligible();
		$this->clearTableReadyCache();
		$this->resetDbReadinessRuntimeState();
	}

	public function tear_down() {
		$this->stopCapturingSchemaProbeQueries();

		if ( static::con() !== null ) {
			$this->restorePluginTimingOptions();
			$this->clearTableReadyCache();
			$this->resetDbReadinessRuntimeState();
		}

		parent::tear_down();
	}

	public function test_shield_extends_core_table_ready_cache_lifetime() :void {
		$this->assertSame(
			DbCon::TABLE_READY_CACHE_LIFETIME,
			apply_filters( 'apto/db/table_ready_cache_lifetime', 30, null )
		);
		$this->assertSame(
			DbCon::TABLE_READY_CACHE_LIFETIME,
			apply_filters( 'apto/db/table_ready_cache_lifetime', 600, null )
		);
	}

	public function test_shield_does_not_reduce_longer_table_ready_cache_lifetime() :void {
		$longerLifetime = DbCon::TABLE_READY_CACHE_LIFETIME + 1;

		$this->assertSame(
			$longerLifetime,
			apply_filters( 'apto/db/table_ready_cache_lifetime', $longerLifetime, null )
		);
	}

	public function test_warm_table_ready_cache_skips_schema_probe_queries() :void {
		$this->clearTableReadyCache();
		$this->resetDbReadinessRuntimeState();

		$coldQueries = $this->captureSchemaProbeQueries( function () :void {
			$this->loadRepresentativeHandlers();
		} );

		$this->assertNotEmpty(
			$coldQueries,
			'Cold DB handler load should perform at least one schema probe.'
		);

		Handler::GetTableReadyCache()->save();
		$this->resetDbReadinessRuntimeState();

		$warmQueries = $this->captureSchemaProbeQueries( function () :void {
			$this->loadRepresentativeHandlers();
		} );

		$this->assertSame(
			[],
			$warmQueries,
			'Warm table-ready cache should skip schema probes. Observed: '.$this->formatSchemaProbeQueries( $warmQueries )
		);
	}

	public function test_missing_table_is_repaired_before_ready_status_is_cacheable() :void {
		$schema = $this->getHandlerSchema( 'events' );

		$this->clearTableReadyCache();
		$this->dropTable( $schema->table );
		$this->resetDbReadinessRuntimeState();

		$handler = $this->requireController()->db_con->load( 'events' );
		$schema = $handler->getTableSchema();

		$this->assertTrue( $handler->isReady(), 'Events handler should be ready after production repair path runs.' );
		$this->assertTrue( $handler->tableExists(), 'Events table should exist after production repair path runs.' );
		$this->assertSame(
			$this->normaliseColumnNames( $schema->getColumnNames() ),
			$this->normaliseColumnNames( Services::WpDb()->getColumnsForTable( $schema->table ) ),
			'Repaired table columns should match the handler schema before the ready cache is usable.'
		);
		$this->assertTrue(
			Handler::GetTableReadyCache()->isReady( $schema ),
			'Ready cache should only report ready after the repaired table reaches a good final state.'
		);
	}

	public function test_ready_cache_removal_does_not_store_invalid_state() :void {
		$schema = $this->getHandlerSchema( 'events' );
		$cache = Handler::GetTableReadyCache();

		$cache->setReady( $schema );
		$cache->save();
		$this->assertTrue( $cache->isReady( $schema ) );

		$cache->setReady( $schema, false );
		$cache->save();

		$this->assertFalse( $cache->isReady( $schema ) );
		$this->assertPersistedReadyCacheContainsOnlyPositiveTimestamps();
	}

	private function makeTableReadyCacheEligible() :void {
		$con = $this->requireController();
		$past = Services::Request()->ts() - \DAY_IN_SECONDS;

		$tracking = $con->plugin->getTracking();
		$tracking->last_upgrade_at = $past;

		$con->opts
			->optSet( 'activated_at', $past )
			->optSet( 'transient_tracking', $tracking->getRawData() );

		if ( $con->opts->hasChanges() ) {
			$con->opts->store();
		}
	}

	private function restorePluginTimingOptions() :void {
		if ( $this->optionSnapshot === [] ) {
			return;
		}

		$con = $this->requireController();
		$trackingData = \is_array( $this->optionSnapshot[ 'transient_tracking' ] )
			? $this->optionSnapshot[ 'transient_tracking' ]
			: [];

		$con->plugin->getTracking()->applyFromArray( $trackingData );
		$con->opts
			->optSet( 'activated_at', $this->optionSnapshot[ 'activated_at' ] )
			->optSet( 'transient_tracking', $this->optionSnapshot[ 'transient_tracking' ] );

		if ( $con->opts->hasChanges() ) {
			$con->opts->store();
		}
	}

	private function clearTableReadyCache() :void {
		\delete_option( TableReadyCache::DB_STATUS_KEY );
		$cache = Handler::GetTableReadyCache();
		$cache->reset();
		$cache->save();
	}

	private function resetDbReadinessRuntimeState() :void {
		$this->requireController()->db_con->reset();
		Services::WpDb()->clearResultShowTables();
	}

	private function loadRepresentativeHandlers() :void {
		foreach ( self::REPRESENTATIVE_DB_KEYS as $dbKey ) {
			$handler = $this->requireController()->db_con->load( $dbKey );
			$this->assertTrue( $handler->isReady(), "DB handler '{$dbKey}' should be ready." );
		}
	}

	private function getHandlerSchema( string $dbKey ) :TableSchema {
		$handler = $this->requireController()->db_con->load( $dbKey );
		$this->assertTrue( $handler->isReady(), "DB handler '{$dbKey}' should be ready." );
		return $handler->getTableSchema();
	}

	private function dropTable( string $table ) :void {
		global $wpdb;

		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
		Services::WpDb()->clearResultShowTables();
	}

	private function captureSchemaProbeQueries( callable $callback ) :array {
		$this->schemaProbeQueries = [];
		$this->queryCaptureHook = function ( string $query ) :string {
			$family = $this->classifySchemaProbeQuery( $query );
			if ( $family !== null ) {
				$this->schemaProbeQueries[] = [
					'family'  => $family,
					'snippet' => $this->compactSql( $query ),
				];
			}
			return $query;
		};

		\add_filter( 'query', $this->queryCaptureHook, \PHP_INT_MAX );
		try {
			$callback();
		}
		finally {
			$this->stopCapturingSchemaProbeQueries();
		}

		return $this->schemaProbeQueries;
	}

	private function stopCapturingSchemaProbeQueries() :void {
		if ( $this->queryCaptureHook !== null ) {
			\remove_filter( 'query', $this->queryCaptureHook, \PHP_INT_MAX );
			$this->queryCaptureHook = null;
		}
	}

	private function classifySchemaProbeQuery( string $query ) :?string {
		$query = \strtoupper( (string)\preg_replace( '/\s+/', ' ', \trim( $query ) ) );

		if ( \preg_match( '/^DESCRIBE\s+/', $query ) === 1 ) {
			return 'DESCRIBE';
		}
		if ( \preg_match( '/^SHOW\s+TABLES\b/', $query ) === 1 ) {
			return 'SHOW TABLES';
		}
		if ( \preg_match( '/^SHOW\s+FULL\s+COLUMNS\b/', $query ) === 1 ) {
			return 'SHOW FULL COLUMNS';
		}
		return null;
	}

	private function normaliseColumnNames( array $columns ) :array {
		$columns = \array_map( '\strtolower', $columns );
		\sort( $columns );
		return $columns;
	}

	private function assertPersistedReadyCacheContainsOnlyPositiveTimestamps() :void {
		$status = \get_option( TableReadyCache::DB_STATUS_KEY, [] );
		$this->assertIsArray( $status, 'Persisted ready cache should be an array when present.' );

		foreach ( $status as $timestamp ) {
			$this->assertIsInt( $timestamp, 'Ready cache values should only be integer timestamps.' );
			$this->assertGreaterThan( 0, $timestamp, 'Ready cache timestamps should only represent positive ready states.' );
		}
	}

	private function formatSchemaProbeQueries( array $queries ) :string {
		return \implode( '; ', \array_map(
			static fn( array $query ) :string => \sprintf( '%s %s', $query[ 'family' ], $query[ 'snippet' ] ),
			\array_slice( $queries, 0, 5 )
		) );
	}

	private function compactSql( string $query ) :string {
		$query = (string)\preg_replace( '/\s+/', ' ', \trim( $query ) );
		return \strlen( $query ) > 140 ? \substr( $query, 0, 140 ).'...' : $query;
	}
}
