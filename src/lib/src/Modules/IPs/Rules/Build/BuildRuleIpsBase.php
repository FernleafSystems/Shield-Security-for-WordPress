<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModConsumer;

abstract class BuildRuleIpsBase extends \FernleafSystems\Wordpress\Plugin\Shield\Rules\Build\BuildRuleCoreShieldBase {

	use ModConsumer;
}