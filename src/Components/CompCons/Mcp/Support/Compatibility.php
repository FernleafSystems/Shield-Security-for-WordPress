<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Support;

use FernleafSystems\Wordpress\Services\Services;

class Compatibility {

	public function supportsAbilitiesIntegration() :bool {
		return $this->isWordPressVersionSupported()
			   && $this->hasAbilitiesApi();
	}

	public function supportsAdapterTransport() :bool {
		return $this->supportsAbilitiesIntegration()
			   && $this->hasAdapterRuntime();
	}

	public function isWordPressVersionSupported() :bool {
		return \version_compare( $this->getWordPressVersion(), '7.0', '>=' );
	}

	public function hasAbilitiesApi() :bool {
		return $this->functionExists( '\wp_register_ability' )
			   && $this->functionExists( '\wp_register_ability_category' );
	}

	public function hasAdapterRuntime() :bool {
		$contract = $this->getAdapterContract();
		$adapterClass = $contract->adapterClass();
		return $this->classExists( $adapterClass )
			   && $this->methodExists( $adapterClass, $contract->adapterBootMethod() )
			   && $this->methodExists( $adapterClass, $contract->adapterCreateServerMethod() )
			   && $this->classExists( $contract->httpTransportClass() )
			   && $this->classExists( $contract->errorHandlerClass() )
			   && $this->classExists( $contract->observabilityHandlerClass() );
	}

	protected function getWordPressVersion() :string {
		return Services::WpGeneral()->getVersion( true );
	}

	protected function functionExists( string $function ) :bool {
		return \function_exists( $function );
	}

	/**
	 * @param class-string $class
	 */
	protected function classExists( string $class ) :bool {
		return \class_exists( $class );
	}

	/**
	 * @param class-string|object $classOrObject
	 */
	protected function methodExists( $classOrObject, string $method ) :bool {
		return \method_exists( $classOrObject, $method );
	}

	protected function getAdapterContract() :WpMcpAdapterContract {
		return new WpMcpAdapterContract();
	}
}
