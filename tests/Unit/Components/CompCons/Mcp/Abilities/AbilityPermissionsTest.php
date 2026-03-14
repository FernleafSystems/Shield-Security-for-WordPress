<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

if ( !\class_exists( 'WP_Error' ) ) {
	class ShieldWpErrorStub {
		private string $code;
		private string $message;
		private array $data;

		public function __construct( string $code = '', string $message = '', $data = [] ) {
			$this->code = $code;
			$this->message = $message;
			$this->data = \is_array( $data ) ? $data : [];
		}

		public function get_error_code() :string {
			return $this->code;
		}

		public function get_error_message() :string {
			return $this->message;
		}

		public function get_error_data() :array {
			return $this->data;
		}
	}

	\class_alias( __NAMESPACE__.'\\ShieldWpErrorStub', 'WP_Error' );
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\Mcp\Abilities;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Abilities\AbilityPermissions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;

class AbilityPermissionsTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->returnArg();
		Functions\when( 'rest_authorization_required_code' )->justReturn( 403 );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_can_execute_returns_true_for_admin_with_rest_level_two() :void {
		Functions\when( 'current_user_can' )->justReturn( true );
		$this->installController( true );

		$this->assertTrue( ( new AbilityPermissions() )->canExecute() );
	}

	public function test_can_execute_returns_wp_error_when_user_cannot_manage_options() :void {
		Functions\when( 'current_user_can' )->justReturn( false );
		$this->installController( true );

		$result = ( new AbilityPermissions() )->canExecute();

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'shield_mcp_permission_denied', $result->get_error_code() );
	}

	public function test_can_execute_returns_wp_error_when_rest_level_two_is_unavailable() :void {
		Functions\when( 'current_user_can' )->justReturn( true );
		$this->installController( false );

		$result = ( new AbilityPermissions() )->canExecute();

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'shield_mcp_capability_unavailable', $result->get_error_code() );
	}

	private function installController( bool $canRestLevel2 ) :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->caps = new class( $canRestLevel2 ) {
			private bool $canRestLevel2;

			public function __construct( bool $canRestLevel2 ) {
				$this->canRestLevel2 = $canRestLevel2;
			}

			public function canRestAPILevel2() :bool {
				return $this->canRestLevel2;
			}
		};

		PluginControllerInstaller::install( $controller );
	}
}
