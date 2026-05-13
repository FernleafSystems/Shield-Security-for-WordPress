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
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestControllerFactory,
	UnitTestPluginUrls
};
use FernleafSystems\Wordpress\Services\Core\{
	General,
	Request
};

class InvestigateByUserRelatedIpCardsBuilderTest extends BaseUnitTest {

	private array $servicesSnapshot = [];
	private RelatedIpCardsRecordingPluginUrls $pluginUrls;

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

		$this->assertSame(
			[ '198.51.100.4', '192.0.2.1', '203.0.113.9' ],
			$this->queryValues( $cards, 'href', 'analyse_ip' )
		);
		$this->assertSame(
			[ '198.51.100.4', '192.0.2.1', '203.0.113.9' ],
			$this->queryValues( $cards, 'investigate_href', 'analyse_ip' )
		);
		$expectedRouteIps = [ '192.0.2.1', '198.51.100.4', '203.0.113.9' ];
		$ipAnalysisCalls = $this->pluginUrls->ipAnalysisCalls;
		$investigateByIpCalls = $this->pluginUrls->investigateByIpCalls;
		\sort( $ipAnalysisCalls );
		\sort( $investigateByIpCalls );
		$this->assertSame( $expectedRouteIps, $ipAnalysisCalls );
		$this->assertSame( $expectedRouteIps, $investigateByIpCalls );
		$this->assertSame( [ 0, 0, 2 ], \array_column( $cards, 'sessions_count' ) );
		$this->assertSame( [ 1, 0, 1 ], \array_column( $cards, 'activity_count' ) );
		$this->assertSame( [ 1, 1, 0 ], \array_column( $cards, 'requests_count' ) );

		foreach ( $cards as $card ) {
			$this->assertArrayNotHasKey( 'has_offense', $card );
			$this->assertArrayHasKey( 'last_seen_at', $card );
			$this->assertArrayHasKey( 'last_seen_ago', $card );
		}
	}

	public function test_build_maps_activity_only_ip_to_default_info_status_contract() :void {
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
		$this->assertArrayHasKey( 'status_label', $cards[ 0 ] );
		$this->assertSame( 1, $cards[ 0 ][ 'activity_count' ] ?? 0 );
		$this->assertSame( 1234, $cards[ 0 ][ 'last_seen_ts' ] ?? 0 );
		$this->assertArrayHasKey( 'last_seen_at', $cards[ 0 ] );
	}

	private function installControllerStub() :void {
		$this->pluginUrls = new RelatedIpCardsRecordingPluginUrls();
		UnitTestControllerFactory::install( $this->pluginUrls );
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

	private function queryValues( array $cards, string $hrefKey, string $queryKey ) :array {
		return \array_map(
			fn( array $card ) :string => (string)( $this->hrefQuery( (string)( $card[ $hrefKey ] ?? '' ) )[ $queryKey ] ?? '' ),
			$cards
		);
	}

	private function hrefQuery( string $href ) :array {
		$query = [];
		\parse_str( (string)\parse_url( $href, \PHP_URL_QUERY ), $query );
		return $query;
	}
}

class RelatedIpCardsRecordingPluginUrls extends UnitTestPluginUrls {

	public array $ipAnalysisCalls = [];
	public array $investigateByIpCalls = [];

	public function ipAnalysis( string $ip ) :string {
		$this->ipAnalysisCalls[] = $ip;
		return parent::ipAnalysis( $ip );
	}

	public function investigateByIp( string $ip = '' ) :string {
		$this->investigateByIpCalls[] = $ip;
		return parent::investigateByIp( $ip );
	}
}

}
