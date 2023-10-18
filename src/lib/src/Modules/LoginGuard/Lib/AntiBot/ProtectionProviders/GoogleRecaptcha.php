<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\ProtectionProviders;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\ReCaptcha\TestRequest;

/**
 * @deprecated 18.5
 */
class GoogleRecaptcha extends BaseProtectionProvider {

	public function setup() {
	}

	public function performCheck( $formProvider ) {
	}

	/**
	 * @return TestRequest
	 */
	protected function getResponseTester() {
		return new TestRequest();
	}

	public function buildFormInsert( $formProvider ) :string {
		return '';
	}

	private function getCaptchaHtml() :string {
		return '';
	}
}