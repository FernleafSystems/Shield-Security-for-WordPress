<?php declare( strict_types=1 );

namespace {
	if ( !\class_exists( 'WP_REST_Request' ) ) {
		class WP_REST_Request {

			/** @var array<string,string> */
			private array $headers;

			/** @var array<string,mixed> */
			private array $params;

			/**
			 * @param array<string,string> $headers
			 * @param array<string,mixed>  $params
			 */
			public function __construct( array $headers = [], array $params = [] ) {
				$this->headers = [];
				foreach ( $headers as $name => $value ) {
					$this->headers[ \strtolower( $name ) ] = $value;
				}
				$this->params = $params;
			}

			public function get_header( string $name ) :string {
				return $this->headers[ \strtolower( $name ) ] ?? '';
			}

			/**
			 * @return array<string,mixed>
			 */
			public function get_json_params() :array {
				return $this->params;
			}
		}
	}

	if ( !\class_exists( 'WP_REST_Response' ) ) {
		class WP_REST_Response {

			/** @var array<string,mixed> */
			private array $data;

			private int $status;

			/**
			 * @param array<string,mixed> $data
			 */
			public function __construct( array $data, int $status = 200 ) {
				$this->data = $data;
				$this->status = $status;
			}

			/**
			 * @return array<string,mixed>
			 */
			public function get_data() :array {
				return $this->data;
			}

			public function get_status() :int {
				return $this->status;
			}
		}
	}
}

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
