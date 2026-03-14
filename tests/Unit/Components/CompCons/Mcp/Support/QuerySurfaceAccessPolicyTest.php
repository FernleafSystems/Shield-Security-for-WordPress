<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\Mcp\Support;

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

if ( !\class_exists( 'WP_REST_Request' ) ) {
	class ShieldWpRestRequestStub {
		public function __construct( string $method = 'GET', string $route = '/' ) {
		}
	}

	\class_alias( __NAMESPACE__.'\\ShieldWpRestRequestStub', 'WP_REST_Request' );
}

if ( !\class_exists( 'WP_REST_Controller' ) ) {
	class ShieldWpRestControllerStub {
	}

	\class_alias( __NAMESPACE__.'\\ShieldWpRestControllerStub', 'WP_REST_Controller' );
}

if ( !\class_exists( 'WP_REST_Server' ) ) {
	class ShieldWpRestServerStub {
		public const READABLE = 'GET';
	}

	\class_alias( __NAMESPACE__.'\\ShieldWpRestServerStub', 'WP_REST_Server' );
}

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Support\QuerySurfaceAccessPolicy;
use FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Route\PostureOverview;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class QuerySurfaceAccessPolicyTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->returnArg();
		Functions\when( 'rest_authorization_required_code' )->justReturn( 403 );
	}

	public function test_is_site_exposure_ready_delegates_to_reference_route() :void {
		$this->assertTrue(
			( new TestQuerySurfaceAccessPolicy( new TestPostureOverviewRoute( true, static fn() :bool => true ) ) )->isSiteExposureReady()
		);
		$this->assertFalse(
			( new TestQuerySurfaceAccessPolicy( new TestPostureOverviewRoute( false, static fn() :bool => true ) ) )->isSiteExposureReady()
		);
	}

	public function test_verify_current_request_returns_true_when_route_permission_allows() :void {
		$result = ( new TestQuerySurfaceAccessPolicy(
			new TestPostureOverviewRoute(
				true,
				static fn( \WP_REST_Request $request ) :bool => $request instanceof \WP_REST_Request
			)
		) )->verifyCurrentRequest();

		$this->assertTrue( $result );
	}

	public function test_verify_current_request_propagates_route_wp_error() :void {
		$result = ( new TestQuerySurfaceAccessPolicy(
			new TestPostureOverviewRoute(
				true,
				static fn() :\WP_Error => new \WP_Error( 'shield_rest_denied', 'Denied by route filter.' )
			)
		) )->verifyCurrentRequest();

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'shield_rest_denied', $result->get_error_code() );
	}

	public function test_verify_current_request_returns_unavailable_error_when_surface_is_not_ready() :void {
		$result = ( new TestQuerySurfaceAccessPolicy(
			new TestPostureOverviewRoute( false, static fn() :bool => true )
		) )->verifyCurrentRequest();

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'shield_query_surface_unavailable', $result->get_error_code() );
	}

	public function test_verify_current_request_returns_permission_denied_error_when_route_returns_false() :void {
		$result = ( new TestQuerySurfaceAccessPolicy(
			new TestPostureOverviewRoute( true, static fn() :bool => false )
		) )->verifyCurrentRequest();

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'shield_query_surface_permission_denied', $result->get_error_code() );
	}
}

class TestQuerySurfaceAccessPolicy extends QuerySurfaceAccessPolicy {

	private PostureOverview $route;

	public function __construct( PostureOverview $route ) {
		$this->route = $route;
	}

	protected function getReferenceRoute() :PostureOverview {
		return $this->route;
	}
}

class TestPostureOverviewRoute extends PostureOverview {

	private bool $available;

	/** @var callable */
	private $permissionCallback;

	public function __construct( bool $available, callable $permissionCallback ) {
		$this->available = $available;
		$this->permissionCallback = $permissionCallback;
	}

	public function isRouteAvailable() :bool {
		return $this->available;
	}

	public function buildRouteDefs() :array {
		return [
			'permission_callback' => $this->permissionCallback,
		];
	}
}
