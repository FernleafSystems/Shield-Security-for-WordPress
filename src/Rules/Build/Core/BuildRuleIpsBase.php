<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build\Core;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

abstract class BuildRuleIpsBase extends BuildRuleCoreShieldBase {

	use PluginControllerConsumer;
}