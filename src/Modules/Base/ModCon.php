<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * @property bool $is_booted
 * @deprecated 19.2
 */
class ModCon extends DynPropertiesClass {

	use PluginControllerConsumer;

	public const SLUG = '';
}