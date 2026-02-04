<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginDelete;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class ResetPlugin {

	use PluginControllerConsumer;

	public function run() {
		self::con()->plugin_reset = true;
		self::con()->opts->resetToDefaults();
		( new PluginDelete() )->run();
	}
}