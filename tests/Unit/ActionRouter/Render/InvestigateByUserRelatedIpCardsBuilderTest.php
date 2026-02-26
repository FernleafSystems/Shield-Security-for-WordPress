<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules {

	if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
		function shield_security_get_plugin() {
			return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
		}
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render {

use Brain\Monkey\Functions;
use Carbon\Carbon;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\InvestigateByUserRelatedIpCardsBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\{
	General,
	Request
};

class InvestigateByUserRelatedIpCardsBuilderTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();

		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );

		$this->servicesSnapshot = ServicesState::snapshot();
		$this->installControllerStub();
		$this->installServicesStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_build_aggregates_statuses_and_sorting_contract() :void {
		$cards = ( new InvestigateByUserRelatedIpCardsBuilder() )->build(
			[
				[
					'ip'               => '203.0.113.9',
					'last_activity_ts' => 1200,
				],
				[
					'ip'               => '203.0.113.9',
					'last_activity_ts' => 1000,
				],
			],
			[
				[
					'ip'            => '203.0.113.9',
					'created_at_ts' => 1500,
				],
				[
					'ip'            => '198.51.100.4',
					'created_at_ts' => 1400,
				],
			],
			[
				[
					'ip'            => '198.51.100.4',
					'created_at_ts' => 1700,
					'offense'       => true,
				],
				[
					'ip'            => '192.0.2.1',
					'created_at_ts' => 1600,
					'offense'       => false,
				],
			]
		);

		$this->assertSame( [ '198.51.100.4', '192.0.2.1', '203.0.113.9' ], \array_column( $cards, 'ip' ) );
		$this->assertSame( [ 1700, 1600, 1500 ], \array_column( $cards, 'last_seen_ts' ) );
		$this->assertSame( [ 'critical', 'warning', 'good' ], \array_column( $cards, 'status' ) );
		$this->assertSame( [ 'Offense Detected', 'Requests Observed', 'Sessions Observed' ], \array_column( $cards, 'status_label' ) );

		$this->assertSame(
			[
				'/admin/'.PluginNavs::NAV_IPS.'/'.PluginNavs::SUBNAV_IPS_RULES.'?analyse_ip=198.51.100.4',
				'/admin/'.PluginNavs::NAV_IPS.'/'.PluginNavs::SUBNAV_IPS_RULES.'?analyse_ip=192.0.2.1',
				'/admin/'.PluginNavs::NAV_IPS.'/'.PluginNavs::SUBNAV_IPS_RULES.'?analyse_ip=203.0.113.9',
			],
			\array_column( $cards, 'href' )
		);
		$this->assertSame(
			[
				'/admin/activity/by_ip?analyse_ip=198.51.100.4',
				'/admin/activity/by_ip?analyse_ip=192.0.2.1',
				'/admin/activity/by_ip?analyse_ip=203.0.113.9',
			],
			\array_column( $cards, 'investigate_href' )
		);
		$this->assertSame( [ 'display:1700', 'display:1600', 'display:1500' ], \array_column( $cards, 'last_seen_at' ) );
		$this->assertSame( [ 0, 0, 2 ], \array_column( $cards, 'sessions_count' ) );
		$this->assertSame( [ 1, 0, 1 ], \array_column( $cards, 'activity_count' ) );
		$this->assertSame( [ 1, 1, 0 ], \array_column( $cards, 'requests_count' ) );

		foreach ( $cards as $card ) {
			$this->assertArrayNotHasKey( 'has_offense', $card );
			$this->assertNotSame( '', (string)( $card[ 'last_seen_ago' ] ?? '' ) );
		}
	}

	public function test_build_maps_activity_only_ip_to_default_info_status_label() :void {
		$cards = ( new InvestigateByUserRelatedIpCardsBuilder() )->build(
			[],
			[
				[
					'ip'            => '203.0.113.60',
					'created_at_ts' => 1234,
				],
			],
			[]
		);

		$this->assertCount( 1, $cards );
		$this->assertSame( '203.0.113.60', $cards[ 0 ][ 'ip' ] ?? '' );
		$this->assertSame( 'info', $cards[ 0 ][ 'status' ] ?? '' );
		$this->assertSame( 'No Recent Signals', $cards[ 0 ][ 'status_label' ] ?? '' );
		$this->assertSame( 1, $cards[ 0 ][ 'activity_count' ] ?? 0 );
		$this->assertSame( 1234, $cards[ 0 ][ 'last_seen_ts' ] ?? 0 );
		$this->assertSame( 'display:1234', $cards[ 0 ][ 'last_seen_at' ] ?? '' );
	}

	private function installControllerStub() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new class {
			public function ipAnalysis( string $ip ) :string {
				return '/admin/'.PluginNavs::NAV_IPS.'/'.PluginNavs::SUBNAV_IPS_RULES.'?analyse_ip='.$ip;
			}

			public function investigateByIp( string $ip = '' ) :string {
				return empty( $ip ) ? '/admin/activity/by_ip' : '/admin/activity/by_ip?analyse_ip='.$ip;
			}
		};
		PluginControllerInstaller::install( $controller );
	}

	private function installServicesStub() :void {
		ServicesState::installItems( [
			'service_request'   => new class extends Request {
				public function carbon( $setTimezone = false, bool $userLocale = true ) :Carbon {
					return Carbon::create( 2026, 2, 26, 12, 0, 0, 'UTC' );
				}
			},
			'service_wpgeneral' => new class extends General {
				public function getTimeStringForDisplay( $ts = null, $bShowTime = true, $bShowDate = true ) {
					return 'display:'.(int)$ts;
				}
			},
		] );
	}
}

}
