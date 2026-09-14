<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\SiteQuery;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\BuildRecentActivity;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\Request;

class BuildRecentActivityTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_request' => new class extends Request {
				public function ts( bool $update = true ) :int {
					return 1700000000;
				}
			},
		] );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_build_filters_to_recent_events_and_marks_missing_records() :void {
		$builder = new BuildRecentActivityTestDouble();
		$query = $builder->build();

		$this->assertSame( [ 'login_success', 'plugin_activated' ], $builder->queriedEventKeys );
		$this->assertSame( 1700000000, $query[ 'generated_at' ] );
		$this->assertSame( [ 'login_success', 'plugin_activated' ], \array_column( $query[ 'items' ], 'key' ) );
		$this->assertSame( [ 1700000100, 0 ], \array_column( $query[ 'items' ], 'latest_at' ) );
		$this->assertSame( [ true, false ], \array_column( $query[ 'items' ], 'has_record' ) );
	}
}

class BuildRecentActivityTestDouble extends BuildRecentActivity {

	public array $queriedEventKeys = [];

	protected function eventsService() {
		return new class {
			public function getEvents() :array {
				return [
					'login_success'    => [ 'recent' => true ],
					'plugin_activated' => [ 'recent' => true ],
					'ignored_event'    => [ 'recent' => false ],
				];
			}

			public function getEventName( string $eventKey ) :string {
				return [
					'login_success'    => 'Successful Login',
					'plugin_activated' => 'Plugin Activated',
					'ignored_event'    => 'Ignored Event',
				][ $eventKey ];
			}
		};
	}

	protected function latestTimestamps( array $eventKeys ) :array {
		$this->queriedEventKeys = $eventKeys;
		return [
			'login_success' => 1700000100,
		];
	}
}
