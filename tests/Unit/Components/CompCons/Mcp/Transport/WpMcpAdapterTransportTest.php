<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\Mcp\Transport;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Support\WpMcpAdapterContract;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Transport\WpMcpAdapterTransport;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class WpMcpAdapterTransportTest extends BaseUnitTest {

	public function test_register_server_hooks_adapter_init_and_creates_explicit_server_definition() :void {
		$callbacks = [];
		$createdServers = [];

		Functions\when( 'add_action' )->alias(
			static function ( string $hook, callable $callback ) use ( &$callbacks ) :bool {
				$callbacks[ $hook ] = $callback;
				return true;
			}
		);

		$adapter = new class( $createdServers ) {
			public array $createdServers = [];

			public function __construct( array $createdServers ) {
				$this->createdServers = $createdServers;
			}

			public function create_server( ...$args ) :void {
				$this->createdServers[] = $args;
			}
		};

		$transport = new class( $adapter ) extends WpMcpAdapterTransport {
			private object $adapter;

			public function __construct( object $adapter ) {
				$this->adapter = $adapter;
			}

			public function isSupported() :bool {
				return true;
			}

			protected function resolveAdapter( $adapter ) {
				unset( $adapter );
				return $this->adapter;
			}

			protected function bootAdapter() :void {
			}

			protected function getContract() :WpMcpAdapterContract {
				return new class extends WpMcpAdapterContract {
					public function httpTransportClass() :string {
						return '\Vendor\HttpTransport';
					}

					public function errorHandlerClass() :string {
						return '\Vendor\ErrorHandler';
					}

					public function observabilityHandlerClass() :string {
						return '\Vendor\ObservabilityHandler';
					}
				};
			}
		};

		$transport->registerServer( [
			'server_id' => 'shield-security',
			'namespace' => 'shield-security',
			'route'     => 'mcp',
			'version'   => '1.2.3',
			'abilities' => [ 'shield/posture/overview/get', 'shield/posture/attention/get' ],
		] );

		$this->assertArrayHasKey( 'mcp_adapter_init', $callbacks );
		$callbacks[ 'mcp_adapter_init' ]( null );

		$this->assertCount( 1, $adapter->createdServers );
		$this->assertSame( [
			'shield-security',
			'shield-security',
			'mcp',
			'1.2.3',
			[ '\Vendor\HttpTransport' ],
			'\Vendor\ErrorHandler',
			'\Vendor\ObservabilityHandler',
			[ 'shield/posture/overview/get', 'shield/posture/attention/get' ],
			[],
			[],
		], $adapter->createdServers[ 0 ] );
	}
}
