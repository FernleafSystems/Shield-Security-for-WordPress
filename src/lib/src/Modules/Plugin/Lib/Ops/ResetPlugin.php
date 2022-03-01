<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

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
