<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Integration;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Transport\{
	McpTransportInterface,
	NullTransport
};

class UnsupportedIntegration extends BaseIntegration {

	private ?McpTransportInterface $transport = null;

	public function isSupported() :bool {
		return false;
	}

	public function register() :void {
	}

	public function getTransport() :McpTransportInterface {
		return $this->transport ??= new NullTransport();
	}
}
