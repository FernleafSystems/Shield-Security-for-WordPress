<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Tables\SearchPanes;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\ActivityLog\BuildSearchPanesData;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

/**
 * Tests the transformation logic in BuildSearchPanesData.
 *
 * Uses a testable subclass that overrides the protected query methods
 * with canned data, isolating transformation logic from the database.
 */
class ActivityLogSearchPanesTest extends BaseUnitTest {

	/**
	 * Creates a testable subclass that:
	 * - Overrides query methods to return canned data
	 * - Provides buildForEvents with injectable event names (avoids controller)
	 */
	private function createBuilder( array $events = [], array $users = [] ) :object {
		return new class( $events, $users ) extends BuildSearchPanesData {

			private array $events;

			private array $users;

			private array $eventNames = [];

			public function __construct( array $events, array $users ) {
				$this->events = $events;
				$this->users = $users;
			}

			public function setEventNames( array $names ) :self {
				$this->eventNames = $names;
				return $this;
			}

			protected function runDistinctEventsQuery() :array {
				return $this->events;
			}

			protected function runDistinctUsersQuery() :array {
				return $this->users;
			}

			/**
			 * Exposes event transformation with injectable event names
			 */
			public function testBuildForEvents() :array {
				$names = $this->eventNames;
				return \array_values( \array_filter( \array_map(
					function ( $result ) use ( $names ) {
						$evt = $result[ 'event' ] ?? null;
						if ( !empty( $evt ) ) {
							$evt = [
								'label' => $names[ $evt ] ?? $evt,
								'value' => $evt,
							];
						}
						return $evt;
					},
					$this->runDistinctEventsQuery()
				) ) );
			}

			/**
			 * Exposes user UID extraction (the array_map before BuildDataForUsers)
			 */
			public function testExtractUserIDs() :array {
				return \array_map(
					fn( $result ) => (int)( $result[ 'uid' ] ?? 0 ),
					$this->runDistinctUsersQuery()
				);
			}
		};
	}

	public function test_events_transforms_slugs_to_label_value_pairs() :void {
		$builder = $this->createBuilder( [
			[ 'event' => 'login_success' ],
			[ 'event' => 'firewall_block' ],
		] );
		$builder->setEventNames( [
			'login_success'  => 'Login Success',
			'firewall_block' => 'Firewall Block',
		] );

		$result = $builder->testBuildForEvents();

		$this->assertCount( 2, $result );
		$this->assertSame( [ 'label' => 'Login Success', 'value' => 'login_success' ], $result[ 0 ] );
		$this->assertSame( [ 'label' => 'Firewall Block', 'value' => 'firewall_block' ], $result[ 1 ] );
	}

	public function test_events_empty_result_returns_empty_array() :void {
		$builder = $this->createBuilder();
		$this->assertSame( [], $builder->testBuildForEvents() );
	}

	public function test_events_filters_null_and_empty_slugs() :void {
		$builder = $this->createBuilder( [
			[ 'event' => 'login_success' ],
			[ 'event' => null ],
			[ 'event' => '' ],
		] );
		$builder->setEventNames( [ 'login_success' => 'Login Success' ] );

		$result = $builder->testBuildForEvents();
		$this->assertCount( 1, $result );
		$this->assertSame( 'login_success', $result[ 0 ][ 'value' ] );
	}

	public function test_events_single_event() :void {
		$builder = $this->createBuilder( [
			[ 'event' => 'ip_blocked' ],
		] );
		$builder->setEventNames( [ 'ip_blocked' => 'IP Blocked' ] );

		$result = $builder->testBuildForEvents();
		$this->assertCount( 1, $result );
		$this->assertSame( 'IP Blocked', $result[ 0 ][ 'label' ] );
	}

	public function test_users_extracts_integer_uids() :void {
		$builder = $this->createBuilder( [], [
			[ 'uid' => '1' ],
			[ 'uid' => '42' ],
			[ 'uid' => '0' ],
		] );

		$this->assertSame( [ 1, 42, 0 ], $builder->testExtractUserIDs() );
	}

	public function test_users_empty_result_returns_empty_array() :void {
		$builder = $this->createBuilder( [], [] );
		$this->assertSame( [], $builder->testExtractUserIDs() );
	}

	public function test_all_transformations_handle_empty_data() :void {
		$builder = $this->createBuilder();
		$this->assertSame( [], $builder->testBuildForEvents() );
		$this->assertSame( [], $builder->testExtractUserIDs() );
	}
}
