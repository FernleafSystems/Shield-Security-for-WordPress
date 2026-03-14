<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\Mcp\Abilities;

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

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Abilities\AbilityPermissions;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Support\QuerySurfaceAccessPolicy;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller
};

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
		$result = ( new TestAbilityPermissions( new FixedAccessPolicy( true ) ) )->canExecute();

		$this->assertTrue( $result );
	}

	public function test_can_execute_returns_wp_error_when_access_policy_denies_request() :void {
		$result = ( new TestAbilityPermissions( new FixedAccessPolicy( new \WP_Error( 'denied', 'Denied.' ) ) ) )->canExecute();

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'denied', $result->get_error_code() );
	}

	public function test_can_execute_returns_wp_error_when_surface_is_unavailable() :void {
		$result = ( new TestAbilityPermissions( new FixedAccessPolicy( new \WP_Error( 'shield_query_surface_unavailable', 'Unavailable.' ) ) ) )->canExecute();

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'shield_query_surface_unavailable', $result->get_error_code() );
	}
}

class FixedAccessPolicy extends QuerySurfaceAccessPolicy {

	/**
	 * @var true|\WP_Error
	 */
	private $result;

	/**
	 * @param true|\WP_Error $result
	 */
	public function __construct( $result ) {
		$this->result = $result;
	}

	public function isSiteExposureReady() :bool {
		return $this->result === true;
	}

	public function verifyCurrentRequest( ?\WP_REST_Request $request = null ) {
		unset( $request );
		return $this->result;
	}
}

class TestAbilityPermissions extends AbilityPermissions {

	private QuerySurfaceAccessPolicy $policy;

	public function __construct( QuerySurfaceAccessPolicy $policy ) {
		$this->policy = $policy;
	}

	protected function getAccessPolicy() :QuerySurfaceAccessPolicy {
		return $this->policy;
	}
}
