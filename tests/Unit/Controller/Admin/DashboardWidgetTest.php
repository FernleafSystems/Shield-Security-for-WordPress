<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Controller\Admin;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\OptsLookup;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Admin\DashboardWidget;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	InvokesNonPublicMethods,
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\Users;

class DashboardWidgetTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'esc_html__' )->alias( static fn( string $text ) :string => $text );
		$this->servicesSnapshot = ServicesState::snapshot();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_can_run_for_wp_admin_in_valid_admin_area_when_widget_enabled() :void {
		$this->installEnvironment( true, true, null );

		$this->assertTrue( $this->canRun() );
	}

	public function test_cannot_run_when_widget_is_not_chosen_for_the_dashboard() :void {
		$this->installEnvironment( true, true, null, true, [ 'notices', 'admin_bar' ] );

		$this->assertFalse( $this->canRun() );
	}

	public function test_cannot_run_when_plugin_property_disables_widget() :void {
		$this->installEnvironment( true, true, null, false );

		$this->assertFalse( $this->canRun() );
	}

	public function test_dashboard_widget_filter_can_show_widget_not_chosen_for_the_dashboard() :void {
		$this->installEnvironment( true, true, true, true, [] );

		$this->assertTrue( $this->canRun() );
	}

	public function test_cannot_run_for_non_admin_user() :void {
		$this->installEnvironment( true, false, true );

		$this->assertFalse( $this->canRun() );
	}

	public function test_cannot_run_when_dashboard_widget_filter_disables_widget() :void {
		$this->installEnvironment( true, true, false );

		$this->assertFalse( $this->canRun() );
	}

	public function test_cannot_run_outside_valid_admin_area() :void {
		$this->installEnvironment( false, true, true );

		$this->assertFalse( $this->canRun() );
	}

	public function test_widget_callback_emits_async_placeholder_without_rendering_summary() :void {
		$this->installEnvironment( true, true, true );

		$callback = null;
		Functions\when( 'wp_add_dashboard_widget' )->alias(
			static function ( string $id, string $title, callable $widgetCallback ) use ( &$callback ) :void {
				$callback = $widgetCallback;
			}
		);

		$this->invokeNonPublicMethod( new DashboardWidget(), 'createWidget' );

		$this->assertIsCallable( $callback );
		\ob_start();
		$callback();
		$output = (string)\ob_get_clean();

		$this->assertStringContainsString( 'id="ShieldDashboardWidget"', $output );
		$this->assertStringContainsString( 'aria-busy="true"', $output );
	}

	private function canRun() :bool {
		return (bool)$this->invokeNonPublicMethod( new DashboardWidget(), 'canRun' );
	}

	/**
	 * @param bool|null $filterResult what the show-widget filter returns; null leaves the default it's given untouched
	 * @param string[]  $dashboardDisplay
	 */
	private function installEnvironment(
		bool $isValidAdminArea,
		bool $isWpAdmin,
		?bool $filterResult,
		bool $propertyShowsWidget = true,
		array $dashboardDisplay = [ 'notices', 'admin_bar', 'dashboard_widget' ]
	) :void {
		Functions\when( 'apply_filters' )->alias(
			static fn( string $hook, $value ) => ( $hook === 'shield/show_dashboard_widget' && $filterResult !== null )
				? $filterResult : $value
		);

		ServicesState::installItems( [
			'service_wpusers' => new class( $isWpAdmin ) extends Users {
				private bool $isWpAdmin;

				public function __construct( bool $isWpAdmin ) {
					$this->isWpAdmin = $isWpAdmin;
				}

				public function isUserAdmin( $user = null ) {
					return $this->isWpAdmin;
				}
			},
		] );

		PluginControllerInstaller::install( new class( $isValidAdminArea, $propertyShowsWidget, $dashboardDisplay ) extends Controller {
			private bool $isValidAdminArea;

			public function __construct( bool $isValidAdminArea, bool $propertyShowsWidget, array $dashboardDisplay ) {
				$this->isValidAdminArea = $isValidAdminArea;
				$this->cfg = (object)[
					'properties' => [
						'show_dashboard_widget' => $propertyShowsWidget,
						'slug_parent'           => 'icwp',
						'slug_plugin'           => 'wpsf',
					],
				];
				$this->opts = new class( $dashboardDisplay ) {
					private array $dashboardDisplay;

					public function __construct( array $dashboardDisplay ) {
						$this->dashboardDisplay = $dashboardDisplay;
					}

					public function optGet( string $key ) {
						return $key === 'dashboard_display' ? $this->dashboardDisplay : null;
					}
				};
				$this->comps = (object)[
					'opts_lookup' => new OptsLookup(),
				];
				$this->labels = (object)[
					'Name' => 'Shield',
				];
				$this->action_router = new class {
					public function render( string $renderAction ) :string {
						throw new \RuntimeException( 'Dashboard widget must load summary asynchronously.' );
					}
				};
			}

			public function isValidAdminArea( bool $checkUserPerms = false ) :bool {
				return $this->isValidAdminArea;
			}
		} );
	}
}
