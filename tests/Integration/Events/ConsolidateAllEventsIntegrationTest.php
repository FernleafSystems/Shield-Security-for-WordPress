<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Events;

use Carbon\Carbon;
use FernleafSystems\Wordpress\Plugin\Shield\Events\ConsolidateAllEvents;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Core\Databases\Ops\TableIndices;
use FernleafSystems\Wordpress\Services\Services;

class ConsolidateAllEventsIntegrationTest extends ShieldIntegrationTestCase {

	private const EVENT_PREFIX = 'reporting_compact_';

	private string $timezoneSnapshot = '';

	/** @var mixed */
	private $gmtOffsetSnapshot;

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'events' );
		$this->timezoneSnapshot = (string)\get_option( 'timezone_string', '' );
		$this->gmtOffsetSnapshot = \get_option( 'gmt_offset', 0 );
		\update_option( 'timezone_string', 'America/New_York' );
		\update_option( 'gmt_offset', 0 );
	}

	public function tear_down() {
		if ( static::con() !== null ) {
			$wpdb = Services::WpDb()->loadWpdb();
			$table = self::con()->db_con->events->getTableSchema()->table;
			$wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE `event` LIKE %s", self::EVENT_PREFIX.'%' ) );
			\delete_option( self::con()->prefix( ConsolidateAllEvents::CURSOR_OPTION ) );
			\delete_transient( $this->guardKey() );
			\delete_site_transient( $this->guardKey() );
			\update_option( 'timezone_string', $this->timezoneSnapshot );
			\update_option( 'gmt_offset', $this->gmtOffsetSnapshot );
		}
		parent::tear_down();
	}

	/** @group database-compat */
	public function test_compaction_preserves_sum_latest_identity_and_timestamp() :void {
		$event = self::EVENT_PREFIX.'sum';
		$start = Carbon::create( 2026, 6, 22, 0, 0, 0, 'America/New_York' );
		$anchorAt = ( clone $start )->addHours( 20 )->timestamp;
		$anchorID = $this->insertEvent( $event, 5, $anchorAt );
		$this->insertEvent( $event, 2, ( clone $start )->addHours( 2 )->timestamp );
		$this->insertEvent( $event, 3, ( clone $start )->addHours( 8 )->timestamp );

		$handler = self::con()->db_con->events;
		$latest = $handler->getQuerySelector()->getLatestForAllEvents();
		$this->assertSame( $anchorID, $latest[ $event ]->id );
		$this->assertSame( $anchorAt, $latest[ $event ]->created_at );
		$this->assertTrue( $handler->compactBoundary( $start->timestamp, ( clone $start )->endOfDay()->timestamp ) );
		$this->assertSame( 10, $this->sumEvent( $event ) );
		$this->assertSame( 1, $this->countEvent( $event ) );

		$record = $handler->getQuerySelector()->filterByEvent( $event )->first();
		$this->assertSame( $anchorID, $record->id );
		$this->assertSame( $anchorAt, $record->created_at );
		$this->assertTrue( $handler->compactBoundary( $start->timestamp, ( clone $start )->endOfDay()->timestamp ) );
		$this->assertSame( 10, $this->sumEvent( $event ) );
	}

	/** @group database-compat */
	public function test_update_and_cleanup_failures_never_create_a_loss_window() :void {
		$event = self::EVENT_PREFIX.'failures';
		$start = Carbon::create( 2026, 6, 23, 0, 0, 0, 'America/New_York' );
		$this->insertEvent( $event, 4, ( clone $start )->addHour()->timestamp );
		$this->insertEvent( $event, 6, ( clone $start )->addHours( 2 )->timestamp );
		$handler = self::con()->db_con->events;
		$wpdb = Services::WpDb()->loadWpdb();
		$oldSuppress = $wpdb->suppress_errors( true );

		$failUpdate = static function ( string $query ) use ( $event ) :string {
			return \stripos( $query, 'UPDATE ' ) !== false && \strpos( $query, $event ) !== false
				? 'UPDATE `shield_missing_table` SET `count`=0'
				: $query;
		};
		\add_filter( 'query', $failUpdate );
		try {
			$this->assertFalse( $handler->compactBoundary( $start->timestamp, ( clone $start )->endOfDay()->timestamp ) );
		}
		finally {
			\remove_filter( 'query', $failUpdate );
		}
		$this->assertSame( 10, $this->sumEvent( $event ) );
		$this->assertSame( 2, $this->countEvent( $event ) );

		$failDelete = static function ( string $query ) use ( $event ) :string {
			return \stripos( $query, 'DELETE FROM ' ) !== false && \strpos( $query, $event ) !== false
				? 'DELETE FROM `shield_missing_table`'
				: $query;
		};
		\add_filter( 'query', $failDelete );
		try {
			$this->assertFalse( $handler->compactBoundary( $start->timestamp, ( clone $start )->endOfDay()->timestamp ) );
		}
		finally {
			\remove_filter( 'query', $failDelete );
			$wpdb->suppress_errors( $oldSuppress );
		}
		$this->assertSame( 10, $this->sumEvent( $event ) );
		$this->assertSame( 2, $this->countEvent( $event ) );

		$this->assertTrue( $handler->compactBoundary( $start->timestamp, ( clone $start )->endOfDay()->timestamp ) );
		$this->assertSame( 10, $this->sumEvent( $event ) );
		$this->assertSame( 1, $this->countEvent( $event ) );
	}

	/** @group database-compat */
	public function test_newer_interleaved_row_survives_captured_id_cleanup() :void {
		$event = self::EVENT_PREFIX.'interleaved';
		$start = Carbon::create( 2026, 6, 24, 0, 0, 0, 'America/New_York' );
		$this->insertEvent( $event, 2, ( clone $start )->addHour()->timestamp );
		$this->insertEvent( $event, 3, ( clone $start )->addHours( 2 )->timestamp );
		$inserted = false;
		$interleave = function ( string $query ) use ( $event, $start, &$inserted ) :string {
			if ( !$inserted && \stripos( $query, 'DELETE FROM ' ) !== false && \strpos( $query, $event ) !== false ) {
				$inserted = true;
				$this->insertEvent( $event, 7, ( clone $start )->addHours( 3 )->timestamp );
			}
			return $query;
		};
		\add_filter( 'query', $interleave );
		try {
			$this->assertTrue( self::con()->db_con->events->compactBoundary(
				$start->timestamp,
				( clone $start )->endOfDay()->timestamp
			) );
		}
		finally {
			\remove_filter( 'query', $interleave );
		}

		$this->assertTrue( $inserted );
		$this->assertSame( 12, $this->sumEvent( $event ) );
		$this->assertSame( 2, $this->countEvent( $event ) );
	}

	public function test_daily_orchestration_compacts_today_recent_and_historical_days() :void {
		$reference = Carbon::create( 2026, 7, 20, 12, 0, 0, 'America/New_York' );
		$events = [];
		foreach ( [ 0, 1, 13, 14 ] as $age ) {
			$event = self::EVENT_PREFIX.'day_'.$age;
			$events[ $event ] = 5 + $age;
			$day = ( clone $reference )->subDays( $age )->startOfDay();
			$this->insertEvent( $event, 2, ( clone $day )->addHour()->timestamp );
			$this->insertEvent( $event, 3 + $age, ( clone $day )->addHours( 20 )->timestamp );
		}

		$this->assertTrue( ( new ConsolidateAllEvents() )->run( $reference ) );
		foreach ( $events as $event => $expectedTotal ) {
			$this->assertSame( 1, $this->countEvent( $event ) );
			$this->assertSame( $expectedTotal, $this->sumEvent( $event ) );
		}
	}

	public function test_daily_compaction_includes_both_repeated_dst_fallback_hours() :void {
		$event = self::EVENT_PREFIX.'dst_fallback';
		$reference = Carbon::create( 2026, 11, 1, 12, 0, 0, 'America/New_York' );
		foreach ( [
			[ 1, Carbon::create( 2026, 11, 1, 5, 10, 0, 'UTC' )->timestamp ],
			[ 2, Carbon::create( 2026, 11, 1, 5, 40, 0, 'UTC' )->timestamp ],
			[ 3, Carbon::create( 2026, 11, 1, 6, 10, 0, 'UTC' )->timestamp ],
			[ 4, Carbon::create( 2026, 11, 1, 6, 40, 0, 'UTC' )->timestamp ],
		] as [ $count, $createdAt ] ) {
			$this->insertEvent( $event, $count, $createdAt );
		}

		$this->assertTrue( ( new ConsolidateAllEvents() )->run( $reference ) );
		$this->assertSame( 1, $this->countEvent( $event ) );
		$this->assertSame( 10, $this->sumEvent( $event ) );
	}

	public function test_current_day_recompaction_merges_later_rows() :void {
		$event = self::EVENT_PREFIX.'current_day';
		$reference = Carbon::create( 2026, 7, 20, 12, 0, 0, 'America/New_York' );
		$day = ( clone $reference )->startOfDay();
		$this->insertEvent( $event, 2, ( clone $day )->addHour()->timestamp );
		$this->insertEvent( $event, 3, ( clone $day )->addHours( 2 )->timestamp );

		$this->assertTrue( ( new ConsolidateAllEvents() )->run( $reference ) );
		$this->assertSame( 1, $this->countEvent( $event ) );
		$this->insertEvent( $event, 7, ( clone $day )->addHours( 10 )->timestamp );

		$this->assertTrue( ( new ConsolidateAllEvents() )->run( $reference ) );
		$this->assertSame( 1, $this->countEvent( $event ) );
		$this->assertSame( 12, $this->sumEvent( $event ) );
	}

	/** @group database-compat */
	public function test_occupied_guard_suppresses_nested_compaction() :void {
		$event = self::EVENT_PREFIX.'guarded_overlap';
		$reference = Carbon::create( 2026, 7, 20, 12, 0, 0, 'America/New_York' );
		$day = ( clone $reference )->startOfDay();
		$this->insertEvent( $event, 2, ( clone $day )->addHour()->timestamp );
		$this->insertEvent( $event, 3, ( clone $day )->addHours( 2 )->timestamp );
		$nested = false;
		$interleave = function ( string $query ) use ( $event, $day, $reference, &$nested ) :string {
			if ( !$nested && \stripos( $query, 'UPDATE ' ) !== false && \strpos( $query, $event ) !== false ) {
				$nested = true;
				$this->insertEvent( $event, 7, ( clone $day )->addHours( 10 )->timestamp );
				$this->assertTrue( ( new ConsolidateAllEvents() )->run( $reference ) );
			}
			return $query;
		};
		\add_filter( 'query', $interleave );
		try {
			$this->assertTrue( ( new ConsolidateAllEvents() )->run( $reference ) );
		}
		finally {
			\remove_filter( 'query', $interleave );
		}

		$this->assertTrue( $nested );
		$this->assertSame( 12, $this->sumEvent( $event ) );
		$this->assertSame( 2, $this->countEvent( $event ) );
		$this->assertTrue( ( new ConsolidateAllEvents() )->run( $reference ) );
		$this->assertSame( 12, $this->sumEvent( $event ) );
		$this->assertSame( 1, $this->countEvent( $event ) );
	}

	public function test_existing_guard_skips_without_changing_events_or_cursor() :void {
		$event = self::EVENT_PREFIX.'guard_skip';
		$reference = Carbon::create( 2026, 7, 20, 12, 0, 0, 'America/New_York' );
		$this->insertEvent( $event, 2, ( clone $reference )->startOfDay()->addHour()->timestamp );
		$this->insertEvent( $event, 3, ( clone $reference )->startOfDay()->addHours( 2 )->timestamp );
		$cursorKey = self::con()->prefix( ConsolidateAllEvents::CURSOR_OPTION );
		\add_option( $cursorKey, 123, '', false );
		\set_transient( $this->guardKey(), 'existing-owner', 600 );

		$this->assertTrue( ( new ConsolidateAllEvents() )->run( $reference ) );
		$this->assertSame( 2, $this->countEvent( $event ) );
		$this->assertSame( 5, $this->sumEvent( $event ) );
		$this->assertSame( 123, (int)\get_option( $cursorKey ) );
	}

	public function test_network_transient_does_not_block_per_site_guard() :void {
		$event = self::EVENT_PREFIX.'site_guard';
		$reference = Carbon::create( 2026, 7, 20, 12, 0, 0, 'America/New_York' );
		$this->insertEvent( $event, 2, ( clone $reference )->startOfDay()->addHour()->timestamp );
		$this->insertEvent( $event, 3, ( clone $reference )->startOfDay()->addHours( 2 )->timestamp );
		\set_site_transient( $this->guardKey(), 'network-owner', 600 );

		$this->assertTrue( ( new ConsolidateAllEvents() )->run( $reference ) );
		$this->assertSame( 1, $this->countEvent( $event ) );
		$this->assertSame( 5, $this->sumEvent( $event ) );
		$this->assertSame( 'network-owner', \get_site_transient( $this->guardKey() ) );
	}

	public function test_run_does_not_delete_replacement_guard() :void {
		$event = self::EVENT_PREFIX.'guard_owner';
		$reference = Carbon::create( 2026, 7, 20, 12, 0, 0, 'America/New_York' );
		$day = ( clone $reference )->startOfDay();
		$this->insertEvent( $event, 2, ( clone $day )->addHour()->timestamp );
		$this->insertEvent( $event, 3, ( clone $day )->addHours( 2 )->timestamp );
		$replacement = 'replacement-owner';
		$replaced = false;
		$replaceGuard = function ( string $query ) use ( $event, $replacement, &$replaced ) :string {
			if ( !$replaced && \stripos( $query, 'UPDATE ' ) !== false && \strpos( $query, $event ) !== false ) {
				$replaced = true;
				\set_transient( $this->guardKey(), $replacement, 600 );
			}
			return $query;
		};
		\add_filter( 'query', $replaceGuard );
		try {
			$this->assertTrue( ( new ConsolidateAllEvents() )->run( $reference ) );
		}
		finally {
			\remove_filter( 'query', $replaceGuard );
		}

		$this->assertTrue( $replaced );
		$this->assertSame( $replacement, \get_transient( $this->guardKey() ) );
	}

	public function test_historical_catchup_is_bounded_and_converges() :void {
		$event = self::EVENT_PREFIX.'catchup';
		$reference = Carbon::create( 2026, 7, 20, 12, 0, 0, 'America/New_York' );
		for ( $day = 0; $day < 16; $day++ ) {
			$start = Carbon::create( 2026, 5, 1, 0, 0, 0, 'America/New_York' )->addDays( $day );
			$this->insertEvent( $event, 1, ( clone $start )->addHour()->timestamp );
			$this->insertEvent( $event, 2, ( clone $start )->addHours( 2 )->timestamp );
		}

		$this->assertTrue( ( new ConsolidateAllEvents() )->run( $reference ) );
		$this->assertSame( 18, $this->countEvent( $event ), 'Fourteen days compacted; two remain as pairs.' );
		$this->assertSame( 48, $this->sumEvent( $event ) );

		$this->assertTrue( ( new ConsolidateAllEvents() )->run( $reference ) );
		$this->assertSame( 16, $this->countEvent( $event ) );
		$this->assertSame( 48, $this->sumEvent( $event ) );
	}

	public function test_historical_cursor_does_not_advance_when_discovery_query_fails() :void {
		$event = self::EVENT_PREFIX.'cursor_failure';
		$reference = Carbon::create( 2026, 7, 20, 12, 0, 0, 'America/New_York' );
		$oldDay = Carbon::create( 2026, 5, 1, 0, 0, 0, 'America/New_York' );
		$this->insertEvent( $event, 2, ( clone $oldDay )->addHour()->timestamp );
		$this->insertEvent( $event, 3, ( clone $oldDay )->addHours( 2 )->timestamp );
		$wpdb = Services::WpDb()->loadWpdb();
		$oldSuppress = $wpdb->suppress_errors( true );
		$failDiscovery = static function ( string $query ) :string {
			return \stripos( $query, 'SELECT MIN(`created_at`)' ) !== false
				? 'SELECT MIN(`created_at`) FROM `shield_missing_table`'
				: $query;
		};
		\add_filter( 'query', $failDiscovery );
		try {
			$this->assertFalse( ( new ConsolidateAllEvents() )->run( $reference ) );
		}
		finally {
			\remove_filter( 'query', $failDiscovery );
			$wpdb->suppress_errors( $oldSuppress );
		}

		$this->assertSame( 0, (int)\get_option( self::con()->prefix( ConsolidateAllEvents::CURSOR_OPTION ), 0 ) );
		$this->assertFalse( \get_transient( $this->guardKey() ) );
		$this->assertSame( 5, $this->sumEvent( $event ) );
		$this->assertSame( 2, $this->countEvent( $event ) );
	}

	public function test_high_cardinality_boundary_reduces_to_one_row_per_event() :void {
		$day = Carbon::create( 2026, 6, 29, 0, 0, 0, 'America/New_York' );
		for ( $i = 0; $i < 100; $i++ ) {
			$event = self::EVENT_PREFIX.'volume_'.\str_pad( (string)$i, 3, '0', \STR_PAD_LEFT );
			$this->insertEvent( $event, 1, ( clone $day )->addHour()->timestamp );
			$this->insertEvent( $event, 2, ( clone $day )->addHours( 2 )->timestamp );
		}

		$this->assertSame( [ 200, 300 ], $this->prefixCardinalityAndTotal( self::EVENT_PREFIX.'volume_' ) );
		$this->assertTrue( self::con()->db_con->events->compactBoundary(
			$day->timestamp,
			( clone $day )->endOfDay()->timestamp
		) );
		$this->assertSame( [ 100, 300 ], $this->prefixCardinalityAndTotal( self::EVENT_PREFIX.'volume_' ) );
	}

	public function test_compaction_queries_use_portable_sql_only() :void {
		$event = self::EVENT_PREFIX.'sql';
		$start = Carbon::create( 2026, 6, 25, 0, 0, 0, 'America/New_York' );
		$this->insertEvent( $event, 1, ( clone $start )->addHour()->timestamp );
		$this->insertEvent( $event, 1, ( clone $start )->addHours( 2 )->timestamp );
		$queries = [];
		$capture = static function ( string $query ) use ( &$queries ) :string {
			$queries[] = $query;
			return $query;
		};
		\add_filter( 'query', $capture );
		try {
			$this->assertTrue( self::con()->db_con->events->compactBoundary(
				$start->timestamp,
				( clone $start )->endOfDay()->timestamp
			) );
		}
		finally {
			\remove_filter( 'query', $capture );
		}

		$sql = \implode( "\n", $queries );
		$this->assertMatchesRegularExpression( '/\bSELECT\b/i', $sql );
		$this->assertMatchesRegularExpression( '/\bUPDATE\b/i', $sql );
		$this->assertMatchesRegularExpression( '/\bDELETE\b/i', $sql );
		$this->assertDoesNotMatchRegularExpression(
			'/(?:\bSTART\s+TRANSACTION\b|\bBEGIN\b|\bCOMMIT\b|\bROLLBACK\b|\bFOR\s+UPDATE\b|\bLOCK\s+TABLES?\b|\bGET_LOCK\s*\(|\bWITH\s+(?:RECURSIVE\s+)?[a-z_][a-z0-9_]*\s+AS\s*\(|\bOVER\s*\(|\bJSON_[A-Z0-9_]+\s*\(|->>?)/i',
			$sql
		);
	}

	/** @group database-compat */
	public function test_grouped_sums_zero_fill_missing_events() :void {
		$event = self::EVENT_PREFIX.'grouped';
		$this->insertEvent( $event, 4, 1000 );
		$this->insertEvent( $event, 6, 2000 );

		$counts = self::con()->db_con->events->getQuerySelector()->sumEventsSeparately( [
			$event,
			self::EVENT_PREFIX.'missing',
		] );

		$this->assertSame( 10, $counts[ $event ] );
		$this->assertSame( 0, $counts[ self::EVENT_PREFIX.'missing' ] );
	}

	/**
	 * @group database-compat
	 * @group database-transaction-exception
	 */
	public function test_daily_database_maintenance_applies_event_range_index_to_existing_table() :void {
		$dbh = self::con()->db_con->events;
		$table = $dbh->getTableSchema()->table;
		$wpdb = Services::WpDb()->loadWpdb();
		$rows = $wpdb->get_results( "SHOW INDEX FROM `{$table}`", ARRAY_A );
		if ( !\is_array( $rows ) ) {
			$this->markTestSkipped( 'Current SQL backend does not expose SHOW INDEX.' );
		}

		$this->runWithPersistentDatabaseMutation(
			function () use ( $rows, $wpdb, $table ) :void {
				if ( \in_array( 'created_at_event', \array_column( $rows, 'Key_name' ), true ) ) {
					$this->assertNotFalse( $wpdb->query( "DROP INDEX `created_at_event` ON `{$table}`" ) );
				}
				$withoutIndex = $wpdb->get_results( "SHOW INDEX FROM `{$table}`", ARRAY_A );
				$this->assertIsArray( $withoutIndex );
				$this->assertNotContains( 'created_at_event', \array_column( $withoutIndex, 'Key_name' ) );

				self::con()->db_con->runDailyCron();
				$restored = $wpdb->get_results( "SHOW INDEX FROM `{$table}`", ARRAY_A );
				$this->assertIsArray( $restored );
				$this->assertContains( 'created_at_event', \array_column( $restored, 'Key_name' ) );
			},
			function () use ( $dbh, $wpdb, $table ) :void {
				( new TableIndices( $dbh->getTableSchema() ) )->applyFromSchema();
				$restored = $wpdb->get_results( "SHOW INDEX FROM `{$table}`", ARRAY_A );
				if ( !\is_array( $restored ) || !\in_array( 'created_at_event', \array_column( $restored, 'Key_name' ), true ) ) {
					throw new \RuntimeException( 'Failed to restore the canonical event range index.' );
				}
			}
		);
	}

	/**
	 * @group database-compat
	 * @group database-transaction-exception
	 */
	public function test_compaction_correctness_does_not_depend_on_optional_index() :void {
		$dbh = self::con()->db_con->events;
		$table = $dbh->getTableSchema()->table;
		$wpdb = Services::WpDb()->loadWpdb();
		$existing = $wpdb->get_results( "SHOW INDEX FROM `{$table}`", ARRAY_A );
		if ( !\is_array( $existing ) ) {
			$this->markTestSkipped( 'Current SQL backend does not expose SHOW INDEX.' );
		}
		$event = self::EVENT_PREFIX.'no_index';
		$start = Carbon::create( 2026, 6, 27, 0, 0, 0, 'America/New_York' );
		$this->runWithPersistentDatabaseMutation(
			function () use ( $existing, $wpdb, $table, $event, $start, $dbh ) :void {
				if ( \in_array( 'created_at_event', \array_column( $existing, 'Key_name' ), true ) ) {
					$this->assertNotFalse( $wpdb->query( "DROP INDEX `created_at_event` ON `{$table}`" ) );
				}
				$withoutIndex = $wpdb->get_results( "SHOW INDEX FROM `{$table}`", ARRAY_A );
				$this->assertIsArray( $withoutIndex );
				$this->assertNotContains( 'created_at_event', \array_column( $withoutIndex, 'Key_name' ) );

				$this->insertEvent( $event, 11, ( clone $start )->addHour()->timestamp );
				$this->insertEvent( $event, 12, ( clone $start )->addHours( 2 )->timestamp );
				$this->assertTrue( $dbh->compactBoundary( $start->timestamp, ( clone $start )->endOfDay()->timestamp ) );
				$this->assertSame( 23, $this->sumEvent( $event ) );
				$this->assertSame( 1, $this->countEvent( $event ) );
			},
			function () use ( $dbh, $wpdb, $table ) :void {
				( new TableIndices( $dbh->getTableSchema() ) )->applyFromSchema();
				$restored = $wpdb->get_results( "SHOW INDEX FROM `{$table}`", ARRAY_A );
				if ( !\is_array( $restored ) || !\in_array( 'created_at_event', \array_column( $restored, 'Key_name' ), true ) ) {
					throw new \RuntimeException( 'Failed to restore the canonical event range index.' );
				}
			}
		);
	}

	/**
	 * @group database-compat
	 * @group database-transaction-exception
	 */
	public function test_compaction_preserves_totals_on_non_transactional_myisam_table() :void {
		$dbh = self::con()->db_con->events;
		$table = $dbh->getTableSchema()->table;
		$wpdb = Services::WpDb()->loadWpdb();
		$status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $table ), ARRAY_A );
		$originalEngine = (string)( $status[ 'Engine' ] ?? '' );
		if ( $originalEngine === '' ) {
			$this->markTestSkipped( 'Current SQL backend does not expose table engines.' );
		}

		$event = self::EVENT_PREFIX.'myisam';
		$start = Carbon::create( 2026, 6, 26, 0, 0, 0, 'America/New_York' );
		$this->runWithPersistentDatabaseMutation(
			function () use ( $wpdb, $table, $event, $start, $dbh ) :void {
				$this->assertNotFalse( $wpdb->query( "ALTER TABLE `{$table}` ENGINE=MyISAM" ) );
				$this->insertEvent( $event, 8, ( clone $start )->addHour()->timestamp );
				$this->insertEvent( $event, 9, ( clone $start )->addHours( 2 )->timestamp );
				$this->assertTrue( $dbh->compactBoundary( $start->timestamp, ( clone $start )->endOfDay()->timestamp ) );
				$this->assertSame( 17, $this->sumEvent( $event ) );
				$this->assertSame( 1, $this->countEvent( $event ) );
			},
			function () use ( $wpdb, $table, $event, $originalEngine ) :void {
				$this->assertNotFalse( $wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE `event` = %s", $event ) ) );
				$this->assertNotFalse( $wpdb->query(
					"ALTER TABLE `{$table}` ENGINE=".\preg_replace( '/[^a-z0-9_]/i', '', $originalEngine )
				) );
			}
		);
	}

	private function insertEvent( string $event, int $count, int $createdAt ) :int {
		$dbh = self::con()->db_con->events;
		$record = $dbh->getRecord();
		$record->event = $event;
		$record->count = $count;
		$record->created_at = $createdAt;
		$dbh->getQueryInserter()->insert( $record );
		return (int)Services::WpDb()->loadWpdb()->insert_id;
	}

	private function sumEvent( string $event ) :int {
		return self::con()->db_con->events->getQuerySelector()->sumEvent( $event );
	}

	private function countEvent( string $event ) :int {
		return self::con()->db_con->events->getQuerySelector()->filterByEvent( $event )->count();
	}

	private function guardKey() :string {
		return self::con()->prefix( ConsolidateAllEvents::GUARD_TRANSIENT );
	}

	/** @return array{int,int} */
	private function prefixCardinalityAndTotal( string $prefix ) :array {
		$dbh = self::con()->db_con->events;
		$wpdb = Services::WpDb()->loadWpdb();
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT COUNT(*) AS `row_count`, SUM(`count`) AS `event_total`
			FROM `{$dbh->getTableSchema()->table}` WHERE `event` LIKE %s",
			$prefix.'%'
		), ARRAY_A );
		return [ (int)( $row[ 'row_count' ] ?? 0 ), (int)( $row[ 'event_total' ] ?? 0 ) ];
	}
}
