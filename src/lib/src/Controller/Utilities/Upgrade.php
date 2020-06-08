<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield;

class Upgrade {

	use Shield\Modules\PluginControllerConsumer;
	use \FernleafSystems\Utilities\Logic\OneTimeExecute;

	protected function run() {
		$oCon = $this->getCon();

		if ( $oCon->getPreviousVersion() !== $oCon->getVersion() ) {
			foreach ( $oCon->modules as $oMod ) {
				$oH = $oMod->getUpgradeHandler();
				if ( $oH instanceof Shield\Modules\Base\Upgrade ) {
					$oH->setMod( $oMod )->execute();
				}
			}
		}

		$oCon->getPluginControllerOptions()->previous_version = $oCon->getVersion();
	}
}