<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Components\CompCons\SiteQuery;

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

		$this->assertSameCanonicalizing( \array_keys( $recentEvents ), \array_keys( $itemsByKey ) );
		$this->assertArrayHasKey( $recordedKey, $itemsByKey );
		$this->assertArrayHasKey( $missingKey, $itemsByKey );
		$this->assertTrue( $itemsByKey[ $recordedKey ][ 'has_record' ] );
		$this->assertGreaterThan( 0, $itemsByKey[ $recordedKey ][ 'latest_at' ] );
		$this->assertFalse( $itemsByKey[ $missingKey ][ 'has_record' ] );
		$this->assertSame( 0, $itemsByKey[ $missingKey ][ 'latest_at' ] );
		$this->assertSame( self::con()->comps->events->getEventName( $recordedKey ), $itemsByKey[ $recordedKey ][ 'label' ] );
		$this->assertSame( self::con()->comps->events->getEventName( $missingKey ), $itemsByKey[ $missingKey ][ 'label' ] );
	}
}
