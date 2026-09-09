<?php declare( strict_types=1 );

namespace MainWP\Child {
	if ( !\class_exists( MainWP_Connect::class ) ) {
		class MainWP_Connect {
			public static bool $throwAuth = false;

			public static $authResponse = false;

			public static array $authCalls = [];

			public static function instance() :self {
				return new self();
			}

			public function auth( $signature, $func, $nonce, $nossl = null ) {
				self::$authCalls[] = [
					'signature' => $signature,
					'func'      => $func,
					'nonce'     => $nonce,
					'nossl'     => $nossl,
				];

				if ( self::$throwAuth ) {
					throw new \RuntimeException( 'MainWP auth failed' );
				}

				return self::$authResponse;
			}
		}
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Integrations\Lib\MainWP\Client\Auth {

	use Brain\Monkey\Functions;
	use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Client\Auth\ReproduceClientAuthByKey;
	use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
	use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
	use FernleafSystems\Wordpress\Services\Core\Request;
	use MainWP\Child\MainWP_Connect;

	class ReproduceClientAuthByKeyTest extends BaseUnitTest {

		private array $servicesSnapshot = [];

		protected function setUp() :void {
			parent::setUp();
			$this->servicesSnapshot = ServicesState::snapshot();
			Functions\when( 'sanitize_text_field' )->alias( static fn( $value ) => \is_string( $value ) ? \trim( $value ) : '' );

			MainWP_Connect::$throwAuth = false;
			MainWP_Connect::$authResponse = false;
			MainWP_Connect::$authCalls = [];
		}

		protected function tearDown() :void {
			ServicesState::restore( $this->servicesSnapshot );
			parent::tearDown();
		}

		public function test_auth_uses_function_request_value() :void {
			$this->installRequest( [
				'mainwpsignature' => 'signed%20value',
				'function'        => 'extra_execution',
				'where'           => 'wp-admin/',
				'nonce'           => '1234',
				'nossl'           => '0',
			] );
			MainWP_Connect::$authResponse = true;

			$this->assertTrue( ReproduceClientAuthByKey::Auth() );
			$this->assertSame( [
				[
					'signature' => 'signed value',
					'func'      => 'extra_execution',
					'nonce'     => '1234',
					'nossl'     => '0',
				],
			], MainWP_Connect::$authCalls );
		}

		public function test_auth_falls_back_to_where_for_login_required_requests() :void {
			$this->installRequest( [
				'mainwpsignature' => 'login-signature',
				'where'           => 'wp-admin/',
				'nonce'           => '5678',
				'nossl'           => '1',
			] );
			MainWP_Connect::$authResponse = true;

			$this->assertTrue( ReproduceClientAuthByKey::Auth() );
			$this->assertSame( 'wp-admin/', MainWP_Connect::$authCalls[ 0 ][ 'func' ] );
		}

		public function test_auth_failure_exception_returns_false() :void {
			$this->installRequest( [
				'mainwpsignature' => 'bad-signature',
				'function'        => 'extra_execution',
				'nonce'           => '9999',
				'nossl'           => '0',
			] );
			MainWP_Connect::$throwAuth = true;

			$this->assertFalse( ReproduceClientAuthByKey::Auth() );
		}

		public function test_auth_normalizes_non_scalar_request_values() :void {
			$this->installRequest( [
				'mainwpsignature' => [ 'signature' ],
				'function'        => [ 'extra_execution' ],
				'where'           => [ 'wp-admin/' ],
				'nonce'           => [ '1234' ],
				'nossl'           => [ '0' ],
			] );

			$this->assertFalse( ReproduceClientAuthByKey::Auth() );
			$this->assertSame( [
				[
					'signature' => '',
					'func'      => '',
					'nonce'     => '',
					'nossl'     => '',
				],
			], MainWP_Connect::$authCalls );
		}

		private function installRequest( array $requestValues ) :void {
			ServicesState::installItems( [
				'service_request' => new class( $requestValues ) extends Request {
					private array $requestValues;

					public function __construct( array $requestValues ) {
						$this->requestValues = $requestValues;
					}

					public function request( $key, $includeCookies = false, $default = null ) {
						unset( $includeCookies );
						return $this->requestValues[ $key ] ?? $default;
					}
				},
			] );
		}
	}
}
