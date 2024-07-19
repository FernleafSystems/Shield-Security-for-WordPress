<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

abstract class Base extends \FernleafSystems\Wordpress\Plugin\Core\Rest\Request\Process {

	use PluginControllerConsumer;
}
