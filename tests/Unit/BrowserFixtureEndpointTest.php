<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit {

	use Brain\Monkey\Functions;
	use Symfony\Component\Filesystem\Path;

	class BrowserFixtureEndpointTest extends BaseUnitTest {

		/** @var callable|null */
		private static $routeCallback;

		protected function tearDown() :void {
			@\unlink( WP_CONTENT_DIR.'/.shield-browser-fixture-token' );
			parent::tearDown();
		}

		public function testEndpointRejectsMissingFixtureToken() :void {
			$response = ( $this->routeCallback() )( new \WP_REST_Request( [], [
				'fixture' => 'actions-queue',
				'action'  => 'seed',
				'args'    => [ 'direct_table' ],
			] ) );

			$this->assertSame( 403, $response->get_status() );
			$this->assertSame( [
				'ok'    => false,
				'error' => [
					'code'    => 'forbidden',
					'message' => 'Invalid browser fixture token.',
				],
			], $response->get_data() );
		}

		public function testEndpointRejectsUnknownFixtureBeforeRuntimeHelpersLoad() :void {
			$this->writeFixtureToken( 'secret' );

			$response = ( $this->routeCallback() )( new \WP_REST_Request(
				[ 'X-Shield-Browser-Fixture-Token' => 'secret' ],
				[
					'fixture' => 'missing-fixture',
					'action'  => 'seed',
					'args'    => [],
				]
			) );

			$this->assertSame( 400, $response->get_status() );
			$this->assertSame( [
				'ok'    => false,
				'error' => [
					'code'    => 'invalid_fixture',
					'message' => 'Unknown browser fixture.',
				],
			], $response->get_data() );
		}

		public function testEndpointRejectsInvalidFixtureActionBeforeRuntimeHelpersLoad() :void {
			$this->writeFixtureToken( 'secret' );

			$response = ( $this->routeCallback() )( new \WP_REST_Request(
				[ 'X-Shield-Browser-Fixture-Token' => 'secret' ],
				[
					'fixture' => 'actions-queue',
					'action'  => 'explode',
					'args'    => [],
				]
			) );

			$this->assertSame( 400, $response->get_status() );
			$this->assertSame( [
				'ok'    => false,
				'error' => [
					'code'    => 'invalid_action',
					'message' => 'Unknown browser fixture action.',
				],
			], $response->get_data() );
		}

		public function testResponseHelperBuildsNormalizedSuccessPayload() :void {
			$this->routeCallback();

			$response = \shield_browser_fixture_success_response(
				'actions-queue',
				'cleanup',
				[ 'cleaned' => true ]
			);

			$this->assertSame( 200, $response->get_status() );
			$this->assertSame( [
				'ok'      => true,
				'fixture' => 'actions-queue',
				'action'  => 'cleanup',
				'data'    => [ 'cleaned' => true ],
			], $response->get_data() );
		}

		public function testResponseHelperBuildsNormalizedErrorPayload() :void {
			$this->routeCallback();

			$response = \shield_browser_fixture_error_response(
				'invalid_action',
				'Unknown browser fixture action.',
				400
			);

			$this->assertSame( 400, $response->get_status() );
			$this->assertSame( [
				'ok'    => false,
				'error' => [
					'code'    => 'invalid_action',
					'message' => 'Unknown browser fixture action.',
				],
			], $response->get_data() );
		}

		public function testAllowedActionsExposeSharedAssertionFixtures() :void {
			$this->routeCallback();

			$actions = \shield_browser_fixture_allowed_actions();

			$this->assertSame( [ 'seed', 'cleanup', 'inspect', 'reset-defaults' ], $actions[ 'dashboard-defaults' ] ?? null );
			$this->assertSame( [ 'seed', 'cleanup', 'inspect' ], $actions[ 'ip-analysis-activity-meta' ] ?? null );
			$this->assertSame( [ 'seed', 'cleanup', 'inspect' ], $actions[ 'ip-rules-table' ] ?? null );
			$this->assertSame( [ 'seed', 'cleanup', 'inspect' ], $actions[ 'security-admin' ] ?? null );
			$this->assertSame( [ 'seed', 'cleanup' ], $actions[ 'security-headers' ] ?? null );
		}

		private function routeCallback() :callable {
			if ( self::$routeCallback === null ) {
				Functions\expect( 'add_action' )
					->once()
					->with( 'rest_api_init', \Mockery::on( static function ( callable $callback ) :bool {
						$callback();
						return true;
					} ) );
				Functions\expect( 'register_rest_route' )
					->once()
					->with(
						'shield-browser-test/v1',
						'/fixture',
						\Mockery::on( static function ( array $route ) :bool {
							self::$routeCallback = $route[ 'callback' ] ?? null;
							return ( $route[ 'methods' ] ?? '' ) === 'POST'
								&& ( $route[ 'permission_callback' ] ?? '' ) === '__return_true'
								&& \is_callable( self::$routeCallback );
						} )
					);

				require_once Path::join( \dirname( __DIR__, 2 ), 'tests/browser/support/shield-browser-fixtures.php' );
			}

			$this->assertIsCallable( self::$routeCallback );
			return self::$routeCallback;
		}

		private function writeFixtureToken( string $token ) :void {
			if ( !\is_dir( WP_CONTENT_DIR ) ) {
				\mkdir( WP_CONTENT_DIR, 0777, true );
			}
			\file_put_contents( WP_CONTENT_DIR.'/.shield-browser-fixture-token', $token );
		}
	}
}
