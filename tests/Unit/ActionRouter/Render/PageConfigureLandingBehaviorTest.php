<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Meters\MeterCard;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageConfigureLanding;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component\Base as MeterComponent;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	InvokesNonPublicMethods,
	PluginControllerInstaller
};

class PageConfigureLandingBehaviorTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	private object $renderCapture;

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_landing_content_renders_hero_and_overview_meter_cards() :void {
		$page = new PageConfigureLandingUnitTestDouble( $this->meterFixturesBySlug() );
		$content = $this->invokeNonPublicMethod( $page, 'getLandingContent' );

		$this->assertSame(
			[
				'action'      => MeterCard::class,
				'action_data' => [
					'meter_slug'    => 'summary',
					'meter_channel' => MeterComponent::CHANNEL_CONFIG,
					'is_hero'       => true,
				],
			],
			$this->renderCapture->calls[ 0 ] ?? []
		);
		$this->assertCount( 4, $this->renderCapture->calls );
		$this->assertMeterRenderCallForSlug( 'ips', $this->meterFixturesBySlug()[ 'ips' ] );
		$this->assertMeterRenderCallForSlug( 'assets', $this->meterFixturesBySlug()[ 'assets' ] );
		$this->assertMeterRenderCallForSlug( 'login', $this->meterFixturesBySlug()[ 'login' ] );

		$this->assertSame( 'rendered-1', $content[ 'hero_meter' ] ?? '' );
		$this->assertCount( 3, $content[ 'overview_meter_cards' ] ?? [] );
		$cardsBySlug = [];
		foreach ( $content[ 'overview_meter_cards' ] ?? [] as $card ) {
			$cardsBySlug[ (string)( $card[ 'slug' ] ?? '' ) ] = $card;
		}
		$this->assertSame( 'good', $cardsBySlug[ 'ips' ][ 'traffic' ] ?? '' );
		$this->assertSame( 'warning', $cardsBySlug[ 'assets' ][ 'traffic' ] ?? '' );
		$this->assertSame( 'critical', $cardsBySlug[ 'login' ][ 'traffic' ] ?? '' );
	}

	public function test_landing_vars_include_stats_and_zone_links() :void {
		$page = new PageConfigureLandingUnitTestDouble( $this->meterFixturesBySlug() );
		$vars = $this->invokeNonPublicMethod( $page, 'getLandingVars' );
		$this->assertSame( [], $this->renderCapture->calls );

		$this->assertSame( 4, \count( $vars[ 'configure_stats' ] ?? [] ) );
		$this->assertSame( 'Good Areas', $vars[ 'configure_stats' ][ 0 ][ 'name' ] ?? '' );
		$this->assertSame( 1, (int)( $vars[ 'configure_stats' ][ 0 ][ 'counts' ][ 'lifetime' ] ?? 0 ) );
		$this->assertSame( 'Needs Work', $vars[ 'configure_stats' ][ 1 ][ 'name' ] ?? '' );
		$this->assertSame( 1, (int)( $vars[ 'configure_stats' ][ 1 ][ 'counts' ][ 'lifetime' ] ?? 0 ) );
		$this->assertSame( 'Critical Areas', $vars[ 'configure_stats' ][ 2 ][ 'name' ] ?? '' );
		$this->assertSame( 1, (int)( $vars[ 'configure_stats' ][ 2 ][ 'counts' ][ 'lifetime' ] ?? 0 ) );
		$this->assertSame( 'Security Zones', $vars[ 'configure_stats' ][ 3 ][ 'name' ] ?? '' );
		$this->assertSame( 2, (int)( $vars[ 'configure_stats' ][ 3 ][ 'counts' ][ 'lifetime' ] ?? 0 ) );

		$this->assertSame(
			[
				[
					'slug'       => 'secadmin',
					'label'      => 'Security Admin',
					'icon_class' => 'bi bi-shield-fill',
					'href'       => '/admin/zones/secadmin',
				],
				[
					'slug'       => 'firewall',
					'label'      => 'Firewall',
					'icon_class' => 'bi bi-shield-shaded',
					'href'       => '/admin/zones/firewall',
				],
			],
			$vars[ 'zone_links' ] ?? []
		);
	}

	public function test_landing_hrefs_preserve_existing_quick_links() :void {
		$page = new PageConfigureLandingUnitTestDouble( $this->meterFixturesBySlug() );
		$hrefs = $this->invokeNonPublicMethod( $page, 'getLandingHrefs' );

		$this->assertSame(
			[
				'grades'       => '/admin/dashboard/grades',
				'zones_home'   => '/admin/zones/secadmin',
				'rules_manage' => '/admin/rules/manage',
				'tools_import' => '/admin/tools/importexport',
			],
			$hrefs
		);
	}

	private function meterFixturesBySlug() :array {
		return [
			'ips'    => [ 'totals' => [ 'percentage' => 91 ] ],
			'assets' => [ 'totals' => [ 'percentage' => 64 ] ],
			'login'  => [ 'totals' => [ 'percentage' => 32 ] ],
		];
	}

	private function installControllerStub() :void {
		$this->renderCapture = (object)[
			'calls' => [],
		];

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new class {
			public function adminTopNav( string $nav, string $subnav = '' ) :string {
				return '/admin/'.$nav.'/'.$subnav;
			}

			public function zone( string $zoneSlug ) :string {
				return '/admin/zones/'.$zoneSlug;
			}
		};
		$controller->svgs = new class {
			public function iconClass( string $icon ) :string {
				return 'bi bi-'.$icon;
			}
		};
		$controller->action_router = new class( $this->renderCapture ) {
			private object $capture;

			public function __construct( object $capture ) {
				$this->capture = $capture;
			}

			public function render( string $action, array $actionData = [] ) :string {
				$this->capture->calls[] = [
					'action'      => $action,
					'action_data' => $actionData,
				];
				return 'rendered-'.\count( $this->capture->calls );
			}
		};
		$controller->comps = (object)[
			'zones' => new class {
				public function getZones() :array {
					return [
						'secadmin' => new class {
							public static function Slug() :string {
								return 'secadmin';
							}

							public function title() :string {
								return 'Security Admin';
							}

							public function icon() :string {
								return 'shield-fill';
							}
						},
						'firewall' => new class {
							public static function Slug() :string {
								return 'firewall';
							}

							public function title() :string {
								return 'Firewall';
							}

							public function icon() :string {
								return 'shield-shaded';
							}
						},
					];
				}
			},
		];

		PluginControllerInstaller::install( $controller );
	}

	private function assertMeterRenderCallForSlug( string $slug, array $meterData ) :void {
		$matches = \array_values( \array_filter(
			$this->renderCapture->calls,
			static function ( array $call ) use ( $slug ) :bool {
				return ( $call[ 'action' ] ?? '' ) === MeterCard::class
					   && (string)( $call[ 'action_data' ][ 'meter_slug' ] ?? '' ) === $slug;
			}
		) );
		$this->assertCount( 1, $matches );
		$this->assertSame( MeterComponent::CHANNEL_CONFIG, $matches[ 0 ][ 'action_data' ][ 'meter_channel' ] ?? '' );
		$this->assertSame( $meterData, $matches[ 0 ][ 'action_data' ][ 'meter_data' ] ?? [] );
	}
}

class PageConfigureLandingUnitTestDouble extends PageConfigureLanding {

	private array $meterFixturesBySlug;

	public function __construct( array $meterFixturesBySlug ) {
		$this->meterFixturesBySlug = $meterFixturesBySlug;
	}

	protected function getConfigureMeterSlugs() :array {
		return \array_keys( $this->meterFixturesBySlug );
	}

	protected function getMeterDataForSlug( string $meterSlug ) :array {
		return $this->meterFixturesBySlug[ $meterSlug ] ?? [ 'totals' => [ 'percentage' => 0 ] ];
	}
}
