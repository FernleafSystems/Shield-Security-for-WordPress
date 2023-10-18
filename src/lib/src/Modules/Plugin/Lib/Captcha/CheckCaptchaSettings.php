<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Captcha;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModConsumer;

/**
 * @deprecated 18.5
 */
class CheckCaptchaSettings {

	use ModConsumer;

	public function checkAll() {
	}

	public function verifyProSettings() {
	}

	public function verifyKeys() {
	}

	private function verifyHcaptcha() :bool {
	}

	private function verifyRecaptcha() :bool {
	}
}
