<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\ProtectionProviders;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\HCaptcha\TestRequest;

class HCaptcha extends GoogleRecaptcha {

	const CAPTCHA_JS_HANDLE = 'icwp-hcaptcha';
	const URL_API = 'https://hcaptcha.com/1/api.js';

	/**
	 * @return TestRequest
	 */
	protected function getResponseTester() {
		return new TestRequest();
	}
}