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
			static fn( string $single, string $plural, int $count ) :string => $count === 1 ? $single : $plural
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

	private function installControllerStubWithQueuePayload( array $queuePayload ) :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
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
