<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Support;

class WpMcpAdapterContract {

	/**
	 * @return class-string
	 */
	public function adapterClass() :string {
		return '\WP\MCP\Core\McpAdapter';
	}

	public function adapterBootMethod() :string {
		return 'instance';
	}

	public function adapterCreateServerMethod() :string {
		return 'create_server';
	}

	/**
	 * @return class-string
	 */
	public function httpTransportClass() :string {
		return '\WP\MCP\Transport\HttpTransport';
	}

	/**
	 * @return class-string
	 */
	public function errorHandlerClass() :string {
		return '\WP\MCP\Handlers\WordPressErrorHandler';
	}

	/**
	 * @return class-string
	 */
	public function observabilityHandlerClass() :string {
		return '\WP\MCP\Handlers\WordPressObservabilityHandler';
	}
}
