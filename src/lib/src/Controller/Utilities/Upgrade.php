<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield;

class Upgrade {

	use Shield\Modules\PluginControllerConsumer;
	use \FernleafSystems\Utilities\Logic\OneTimeExecute;

	protected function run() {
		$con = $this->getCon();

		if ( $con->cfg->previous_version !== $con->getVersion() ) {
			foreach ( $con->modules as $mod ) {
				$H = $mod->getUpgradeHandler();
				if ( $H instanceof Shield\Modules\Base\Upgrade ) {
					$H->execute();
				}
			}
		}

		$con->cfg->previous_version = $con->getVersion();
	}
}