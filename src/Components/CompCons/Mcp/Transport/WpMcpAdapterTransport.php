<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Transport;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Support\Compatibility;

class WpMcpAdapterTransport implements McpTransportInterface {

	public function isSupported() :bool {
		return ( new Compatibility() )->supportsAdapterTransport();
	}

	public function registerServer( array $serverDefinition ) :void {
		if ( !$this->isSupported() ) {
			return;
		}

		\add_action( 'mcp_adapter_init', function ( $adapter = null ) use ( $serverDefinition ) :void {
			$adapter = $this->resolveAdapter( $adapter );
			if ( !\is_object( $adapter ) || !\method_exists( $adapter, 'create_server' ) ) {
				return;
			}

			$adapter->create_server(
				$serverDefinition[ 'server_id' ],
				$serverDefinition[ 'namespace' ],
				$serverDefinition[ 'route' ],
				$serverDefinition[ 'version' ],
				[ $this->httpTransportClass() ],
				$this->errorHandlerClass(),
				$this->observabilityHandlerClass(),
				$serverDefinition[ 'abilities' ],
				[],
				[]
			);
		}, 10, 1 );

		$this->bootAdapter();
	}

	public function getIdentifier() :string {
		return 'wp_mcp_adapter';
	}

	/**
	 * @param mixed $adapter
	 * @return mixed
	 */
	protected function resolveAdapter( $adapter ) {
		if ( \is_object( $adapter ) && \method_exists( $adapter, 'create_server' ) ) {
			return $adapter;
		}

		$adapterClass = $this->adapterClass();
		if ( \class_exists( $adapterClass ) && \method_exists( $adapterClass, 'instance' ) ) {
			return $adapterClass::instance();
		}

		return null;
	}

	protected function bootAdapter() :void {
		$adapterClass = $this->adapterClass();
		if ( \class_exists( $adapterClass ) && \method_exists( $adapterClass, 'instance' ) ) {
			$adapterClass::instance();
		}
	}

	protected function adapterClass() :string {
		return '\WP\MCP\Core\McpAdapter';
	}

	protected function httpTransportClass() :string {
		return '\WP\MCP\Transport\HttpTransport';
	}

	protected function errorHandlerClass() :string {
		return '\WP\MCP\Handlers\WordPressErrorHandler';
	}

	protected function observabilityHandlerClass() :string {
		return '\WP\MCP\Handlers\WordPressObservabilityHandler';
	}
}
