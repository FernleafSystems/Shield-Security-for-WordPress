<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\ProtectionProviders;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\HCaptcha\TestRequest;

/**
 * @deprecated 18.5
 */
class HCaptcha extends GoogleRecaptcha {

	/**
	 * @return TestRequest
	 */
	protected function getResponseTester() {
		return new TestRequest();
	}
}