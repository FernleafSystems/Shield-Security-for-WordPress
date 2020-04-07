<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Captcha;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class VerifySettings {

	use ModConsumer;

	public function verify() {
		/** @var \ICWP_WPSF_FeatureHandler_Plugin $oMod */
		$oMod = $this->getMod();
		$oCfg = $oMod->getCaptchaConfig();
	}
}
