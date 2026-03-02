<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageOperatorModeLanding;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	InvokesNonPublicMethods,
	PluginControllerInstaller
};

class PageOperatorModeLandingBehaviorTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count, ...$unused ) :string => $count === 1 ? $single : $plural
		);
		$this->installControllerStubWithQueuePayload( [] );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_build_actions_hero_single_item_uses_singular_copy() :void {
		$page = new PageOperatorModeLanding();
		$hero = $this->invokeNonPublicMethod( $page, 'buildActionsHero', [
			[
				'has_items'   => true,
				'total_items' => 1,
				'severity'    => 'critical',
				'icon_class'  => 'bi bi-exclamation-triangle-fill',
				'subtext'     => 'Last scan: 2 minutes ago',
			],
		] );

		$this->assertSame( 'critical', $hero[ 'severity' ] ?? '' );
		$this->assertSame( 'critical', $hero[ 'badge_status' ] ?? '' );
		$this->assertSame( 'bi bi-exclamation-triangle-fill', $hero[ 'icon_class' ] ?? '' );
		$this->assertSame( 'Last scan: 2 minutes ago', $hero[ 'meta' ] ?? '' );
		$this->assertSame( '1 item', $hero[ 'badge_text' ] ?? '' );
		$this->assertStringContainsString( '1 issue needs your attention', $hero[ 'subtitle' ] ?? '' );
	}

	public function test_build_actions_hero_uses_normalized_summary_contract() :void {
		$page = new PageOperatorModeLanding();
		$hero = $this->invokeNonPublicMethod( $page, 'buildActionsHero', [
			[
				'has_items'   => true,
				'total_items' => 2,
				'severity'    => 'warning',
				'icon_class'  => 'bi bi-exclamation-triangle-fill',
				'subtext'     => 'Last scan: 4 minutes ago',
			],
		] );

		$this->assertSame( 'warning', $hero[ 'severity' ] ?? '' );
		$this->assertSame( 'warning', $hero[ 'badge_status' ] ?? '' );
		$this->assertSame( 'bi bi-exclamation-triangle-fill', $hero[ 'icon_class' ] ?? '' );
		$this->assertSame( 'Last scan: 4 minutes ago', $hero[ 'meta' ] ?? '' );
		$this->assertSame( '2 items', $hero[ 'badge_text' ] ?? '' );
		$this->assertStringContainsString( '2 issues need your attention', $hero[ 'subtitle' ] ?? '' );
	}

	public function test_build_actions_hero_all_clear_branch_uses_good_defaults() :void {
		$page = new PageOperatorModeLanding();
		$hero = $this->invokeNonPublicMethod( $page, 'buildActionsHero', [
			[
				'has_items'   => false,
				'total_items' => 0,
				'severity'    => 'good',
				'icon_class'  => 'bi bi-shield-check',
				'subtext'     => '',
			],
		] );

		$this->assertSame( 'good', $hero[ 'severity' ] ?? '' );
		$this->assertSame( 'good', $hero[ 'badge_status' ] ?? '' );
		$this->assertSame( 'bi bi-shield-check', $hero[ 'icon_class' ] ?? '' );
		$this->assertSame( 'All clear', $hero[ 'badge_text' ] ?? '' );
		$this->assertSame( 'All clear - no issues require your attention', $hero[ 'subtitle' ] ?? '' );
	}

	public function test_queue_summary_is_loaded_from_render_data_contract_path() :void {
		$this->installControllerStubWithQueuePayload( [
			'vars'        => [
				'summary' => [
					'has_items'   => false,
					'total_items' => 0,
					'severity'    => 'good',
					'icon_class'  => 'wrong-path',
					'subtext'     => 'wrong-path',
				],
			],
			'render_data' => [
				'vars' => [
					'summary' => [
						'has_items'   => true,
						'total_items' => 3,
						'severity'    => 'warning',
						'icon_class'  => 'from-render-data',
						'subtext'     => 'from-render-data',
					],
				],
			],
		] );

		$page = new PageOperatorModeLanding();
		$summary = $this->invokeNonPublicMethod( $page, 'getQueueSummary' );

		$this->assertSame( true, $summary[ 'has_items' ] );
		$this->assertSame( 3, $summary[ 'total_items' ] );
		$this->assertSame( 'warning', $summary[ 'severity' ] );
		$this->assertSame( 'from-render-data', $summary[ 'icon_class' ] );
		$this->assertSame( 'from-render-data', $summary[ 'subtext' ] );
	}

	public function test_build_investigate_badge_text_handles_zero_singular_and_plural() :void {
		$page = new PageOperatorModeLanding();

		$this->assertSame( '', $this->invokeNonPublicMethod( $page, 'buildInvestigateBadgeText', [ 0 ] ) );
		$this->assertSame( '1 active session', $this->invokeNonPublicMethod( $page, 'buildInvestigateBadgeText', [ 1 ] ) );
		$this->assertSame( '4 active sessions', $this->invokeNonPublicMethod( $page, 'buildInvestigateBadgeText', [ 4 ] ) );
	}

	public function test_build_reports_badge_text_handles_zero_singular_and_plural() :void {
		$page = new PageOperatorModeLanding();

		$this->assertSame( '', $this->invokeNonPublicMethod( $page, 'buildReportsBadgeText', [ 0 ] ) );
		$this->assertSame( '1 report', $this->invokeNonPublicMethod( $page, 'buildReportsBadgeText', [ 1 ] ) );
		$this->assertSame( '5 reports', $this->invokeNonPublicMethod( $page, 'buildReportsBadgeText', [ 5 ] ) );
	}

	public function test_build_mode_strip_includes_live_badges_and_config_badge_label() :void {
		$page = new PageOperatorModeLanding();
		$strip = $this->invokeNonPublicMethod( $page, 'buildModeStrip', [ 72, 'warning', '3 active sessions', '8 reports' ] );

		$this->assertSame( '3 active sessions', $strip[ 0 ][ 'badge_text' ] ?? '' );
		$this->assertSame( '72%', $strip[ 1 ][ 'badge_text' ] ?? '' );
		$this->assertSame( 'Config Score', $strip[ 1 ][ 'badge_label' ] ?? '' );
		$this->assertSame( '8 reports', $strip[ 2 ][ 'badge_text' ] ?? '' );
	}

	public function test_build_mode_strip_keeps_badges_empty_when_no_live_data() :void {
		$page = new PageOperatorModeLanding();
		$strip = $this->invokeNonPublicMethod( $page, 'buildModeStrip', [ 72, 'warning', '', '' ] );

		$this->assertSame( '', $strip[ 0 ][ 'badge_text' ] ?? 'not-empty' );
		$this->assertSame( '', $strip[ 2 ][ 'badge_text' ] ?? 'not-empty' );
	}

	private function installControllerStubWithQueuePayload( array $queuePayload ) :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new class {
			public function adminTopNav( string $nav, string $subnav = '' ) :string {
				return '/admin/'.$nav.'/'.$subnav;
			}

			public function noncedPluginAction( string $action, string $redirectUrl ) :string {
				return '/action/'.$action.'?redirect='.urlencode( $redirectUrl );
			}
		};
		$controller->svgs = new class {
			public function iconClass( string $icon ) :string {
				return 'bi bi-'.$icon;
			}
		};
		$controller->action_router = new class( $queuePayload ) {
			private array $queuePayload;

			public function __construct( array $queuePayload ) {
				$this->queuePayload = $queuePayload;
			}

			public function action( string $class ) :object {
				return new class( $this->queuePayload ) {
					private array $queuePayload;

					public function __construct( array $queuePayload ) {
						$this->queuePayload = $queuePayload;
					}

					public function payload() :array {
						return $this->queuePayload;
					}
				};
			}
		};
		PluginControllerInstaller::install( $controller );
	}
}
