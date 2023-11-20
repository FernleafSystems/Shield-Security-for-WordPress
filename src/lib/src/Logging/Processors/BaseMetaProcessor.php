<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Logging\Processors;

use AptowebDeps\Monolog\Processor\ProcessorInterface;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

abstract class BaseMetaProcessor implements ProcessorInterface {

	use PluginControllerConsumer;
}