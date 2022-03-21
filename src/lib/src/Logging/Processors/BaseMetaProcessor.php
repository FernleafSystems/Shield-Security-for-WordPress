<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Logging\Processors;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use Monolog\Processor\ProcessorInterface;

abstract class BaseMetaProcessor implements ProcessorInterface {

	use PluginControllerConsumer;
}