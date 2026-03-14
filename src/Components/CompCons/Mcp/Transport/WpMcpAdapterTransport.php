<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Transport;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Support\Compatibility;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Support\WpMcpAdapterContract;

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
			if ( !\is_object( $adapter ) || !\method_exists( $adapter, $this->getContract()->adapterCreateServerMethod() ) ) {
				return;
			}

			$method = $this->getContract()->adapterCreateServerMethod();
			$adapter->{$method}(
				$serverDefinition[ 'server_id' ],
				$serverDefinition[ 'namespace' ],
				$serverDefinition[ 'route' ],
				$serverDefinition[ 'version' ],
				[ $this->getContract()->httpTransportClass() ],
				$this->getContract()->errorHandlerClass(),
				$this->getContract()->observabilityHandlerClass(),
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
		$contract = $this->getContract();
		if ( \is_object( $adapter ) && \method_exists( $adapter, $contract->adapterCreateServerMethod() ) ) {
			return $adapter;
		}

		$adapterClass = $contract->adapterClass();
		$bootMethod = $contract->adapterBootMethod();
		if ( \class_exists( $adapterClass ) && \method_exists( $adapterClass, $bootMethod ) ) {
			return $adapterClass::{$bootMethod}();
		}

		return null;
	}

	protected function bootAdapter() :void {
		$contract = $this->getContract();
		$adapterClass = $contract->adapterClass();
		$bootMethod = $contract->adapterBootMethod();
		if ( \class_exists( $adapterClass ) && \method_exists( $adapterClass, $bootMethod ) ) {
			$adapterClass::{$bootMethod}();
		}
	}

	protected function getContract() :WpMcpAdapterContract {
		return new WpMcpAdapterContract();
	}
}
