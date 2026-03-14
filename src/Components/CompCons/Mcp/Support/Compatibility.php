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
		$adapterClass = $this->adapterClass();
		return $this->classExists( $adapterClass )
			   && $this->methodExists( $adapterClass, 'instance' )
			   && $this->methodExists( $adapterClass, 'create_server' )
			   && $this->classExists( $this->httpTransportClass() )
			   && $this->classExists( $this->errorHandlerClass() )
			   && $this->classExists( $this->observabilityHandlerClass() );
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

	/**
	 * @return class-string
	 */
	protected function adapterClass() :string {
		return '\WP\MCP\Core\McpAdapter';
	}

	/**
	 * @return class-string
	 */
	protected function httpTransportClass() :string {
		return '\WP\MCP\Transport\HttpTransport';
	}

	/**
	 * @return class-string
	 */
	protected function errorHandlerClass() :string {
		return '\WP\MCP\Handlers\WordPressErrorHandler';
	}

	/**
	 * @return class-string
	 */
	protected function observabilityHandlerClass() :string {
		return '\WP\MCP\Handlers\WordPressObservabilityHandler';
	}
}
