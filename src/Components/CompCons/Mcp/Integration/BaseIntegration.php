<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Integration;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Transport\McpTransportInterface;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

abstract class BaseIntegration {

	use PluginControllerConsumer;

	abstract public function isSupported() :bool;

	abstract public function register() :void;

	abstract public function getTransport() :McpTransportInterface;
}
