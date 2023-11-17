<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown\ModConsumer;

abstract class BuildRuleLockdownBase extends \FernleafSystems\Wordpress\Plugin\Shield\Rules\Build\BuildRuleCoreShieldBase {

	use ModConsumer;
}