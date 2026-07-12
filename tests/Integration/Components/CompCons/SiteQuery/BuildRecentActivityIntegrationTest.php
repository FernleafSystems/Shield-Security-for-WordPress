<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Components\CompCons\SiteQuery;

use Carbon\Carbon;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\BuildRecentActivity;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class BuildRecentActivityIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'events' );
		$this->loginAsSecurityAdmin();
	}

	public function test_recent_activity_uses_live_recent_event_definitions_and_record_lookup() :void {
		$recentEvents = \array_filter(
			self::con()->comps->events->getEvents(),
			static fn( array $event ) :bool => !empty( $event[ 'recent' ] )
		);

		if ( \count( $recentEvents ) < 2 ) {
			$this->markTestSkipped( 'At least two recent events are required for recent activity integration coverage.' );
		}

		$recentKeys = \array_values( \array_keys( $recentEvents ) );
		$recordedKey = $recentKeys[ 0 ];
		$missingKey = $recentKeys[ 1 ];

		$this->assertTrue( self::con()->db_con->events->commitEvent( $recordedKey ) );

		$query = self::con()->comps->site_query->recentActivity();
		$itemsByKey = [];
		foreach ( $query[ 'items' ] as $item ) {
			$itemsByKey[ $item[ 'key' ] ] = $item;
		}

		$this->assertEqualsCanonicalizing( \array_keys( $recentEvents ), \array_keys( $itemsByKey ) );
		$this->assertArrayHasKey( $recordedKey, $itemsByKey );
		$this->assertArrayHasKey( $missingKey, $itemsByKey );
		$this->assertTrue( $itemsByKey[ $recordedKey ][ 'has_record' ] );
		$this->assertGreaterThan( 0, $itemsByKey[ $recordedKey ][ 'latest_at' ] );
		$this->assertFalse( $itemsByKey[ $missingKey ][ 'has_record' ] );
		$this->assertSame( 0, $itemsByKey[ $missingKey ][ 'latest_at' ] );
		$this->assertSame( self::con()->comps->events->getEventName( $recordedKey ), $itemsByKey[ $recordedKey ][ 'label' ] );
		$this->assertSame( self::con()->comps->events->getEventName( $missingKey ), $itemsByKey[ $missingKey ][ 'label' ] );
	}

	/** @group database-compat */
	public function test_compaction_preserves_latest_real_occurrence_timestamp() :void {
		$recentEvents = \array_filter(
			self::con()->comps->events->getEvents(),
			static fn( array $event ) :bool => !empty( $event[ 'recent' ] )
		);
		$event = (string)\array_key_first( $recentEvents );
		if ( $event === '' ) {
			$this->markTestSkipped( 'A recent event definition is required.' );
		}
		$dbh = self::con()->db_con->events;
		$wpdb = \FernleafSystems\Wordpress\Services\Services::WpDb()->loadWpdb();
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM `{$dbh->getTableSchema()->table}` WHERE `event` = %s",
			$event
		) );

		$day = Carbon::create( 2026, 6, 22, 0, 0, 0, 'UTC' );
		$latestAt = ( clone $day )->addHours( 20 )->timestamp;
		$this->insertEvent( $event, 3, $latestAt );
		$this->insertEvent( $event, 2, ( clone $day )->addHours( 2 )->timestamp );
		$before = $this->recentItem( $event );

		$this->assertTrue( self::con()->db_con->events->compactBoundary(
			$day->timestamp,
			( clone $day )->endOfDay()->timestamp
		) );
		$after = $this->recentItem( $event );

		$this->assertSame( $latestAt, $before[ 'latest_at' ] );
		$this->assertSame( $before[ 'latest_at' ], $after[ 'latest_at' ] );
		$this->assertSame( 1, $dbh->getQuerySelector()->filterByEvent( $event )->count() );
		$this->assertSame( 5, $dbh->getQuerySelector()->sumEvent( $event ) );
	}

	private function insertEvent( string $event, int $count, int $createdAt ) :void {
		$dbh = self::con()->db_con->events;
		$record = $dbh->getRecord();
		$record->event = $event;
		$record->count = $count;
		$record->created_at = $createdAt;
		$dbh->getQueryInserter()->insert( $record );
	}

	private function recentItem( string $event ) :array {
		foreach ( ( new BuildRecentActivity() )->build()[ 'items' ] as $item ) {
			if ( $item[ 'key' ] === $event ) {
				return $item;
			}
		}
		$this->fail( 'Recent activity item not found: '.$event );
	}
}
