<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\HCaptcha;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\ReCaptcha;

/**
 * @deprecated 18.5
 */
class TestRequest extends ReCaptcha\TestRequest {

	public const URL_VERIFY = 'https://hcaptcha.com/siteverify';

	/**
	 * @throws \Exception
	 */
	protected function runTest() :bool {
		return true;
	}
}