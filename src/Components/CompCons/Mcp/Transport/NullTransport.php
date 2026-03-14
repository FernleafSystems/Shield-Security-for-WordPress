<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Transport;

class NullTransport implements McpTransportInterface {

	public function isSupported() :bool {
		return false;
	}

	public function registerServer( array $serverDefinition ) :void {
		unset( $serverDefinition );
	}

	public function getIdentifier() :string {
		return 'null';
	}
}
