<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Processor extends BaseShield\Processor {

	protected function run() {
		self::con()->getModule_License()->getLicenseHandler()->execute();
		( new Lib\PluginNameSuffix() )->execute();
	}
}