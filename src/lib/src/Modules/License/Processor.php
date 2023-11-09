<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License;

class Processor extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Processor {

	protected function run() {
		self::con()->getModule_License()->getLicenseHandler()->execute();
		( new Lib\PluginNameSuffix() )->execute();
	}
}