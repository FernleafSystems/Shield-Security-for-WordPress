<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Checks;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * All Plugin Modules have been created at this stage, we now run some prechecks to ensure we're ok to do full modules
 * boot.
 */
class PreModulesBootCheck {

	use PluginControllerConsumer;

	public function run() {
		$con = $this->getCon();

		$modData = $con->getModule_Data();
		if ( $modData->getDbH_IPs()->isReady() ) {
		}
	}
}
