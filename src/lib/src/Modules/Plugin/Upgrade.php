<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Upgrade extends Base\Upgrade {

	protected function runEveryUpgrade() {
		/** @var \ICWP_WPSF_FeatureHandler_Plugin $oMod */
		$oMod = $this->getMod();
		$oMod->deleteAllPluginCrons();
	}
}