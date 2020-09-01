<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * Class ResetPlugin
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Ops
 */
class ResetPlugin {

	use PluginControllerConsumer;

	public function run() {
		foreach ( $this->getCon()->modules as $mod ) {
			$mod->getOptions()
				->setOptionsValues( [] )
				->deleteStorage();
			$mod->saveModOptions();
		}
	}
}
