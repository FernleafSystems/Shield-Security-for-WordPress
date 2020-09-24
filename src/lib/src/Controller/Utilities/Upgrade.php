<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield;

class Upgrade {

	use Shield\Modules\PluginControllerConsumer;
	use \FernleafSystems\Utilities\Logic\OneTimeExecute;

	protected function run() {
		$con = $this->getCon();

		if ( $con->getPreviousVersion() !== $con->getVersion() ) {
			foreach ( $con->modules as $mod ) {
				$H = $mod->getUpgradeHandler();
				if ( $H instanceof Shield\Modules\Base\Upgrade ) {
					$H->setMod( $mod )->execute();
				}
			}
		}

		$con->getPluginControllerOptions()->previous_version = $con->getVersion();
	}
}