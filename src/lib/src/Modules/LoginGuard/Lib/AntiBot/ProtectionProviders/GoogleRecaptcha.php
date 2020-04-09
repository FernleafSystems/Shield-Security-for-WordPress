<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\ProtectionProviders;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\ReCaptcha\TestRequest;

class GoogleRecaptcha extends BaseProtectionProvider {

	const CAPTCHA_JS_HANDLE = 'icwp-google-recaptcha';
	const URL_API = 'https://www.google.com/recaptcha/api.js';

	public function setup() {
		$this->getCon()
			 ->getModule_Plugin()
			 ->getCaptchaEnqueue()
			 ->setToEnqueue();
	}

	/**
	 * @inheritDoc
	 */
	public function performCheck( $oForm ) {
		if ( !$this->isFactorTested() ) {
			$this->setFactorTested( true );
			try {
				$this->getResponseTester()
					 ->setMod( $this->getMod() )
					 ->test();
			}
			catch ( \Exception $oE ) {
				$this->processFailure();
				throw $oE;
			}
		}
	}

	/**
	 * @return TestRequest
	 */
	protected function getResponseTester() {
		return new TestRequest();
	}

	/**
	 * @inheritDoc
	 */
	public function buildFormInsert( $oFormProvider ) {
		return $this->getCaptchaHtml();
	}

	/**
	 * @return string
	 */
	private function getCaptchaHtml() {
		/** @var \ICWP_WPSF_FeatureHandler_LoginProtect $oMod */
		$oMod = $this->getMod();
		if ( $oMod->getCaptchaCfg()->invisible ) {
			$sExtraStyles = '';
		}
		else {
			$sExtraStyles = '<style>@media screen {#rc-imageselect, .icwpg-recaptcha iframe {transform:scale(0.895);-webkit-transform:scale(0.895);transform-origin:0 0;-webkit-transform-origin:0 0;}</style>';
		}
		return $sExtraStyles.$this->getCon()
								  ->getModule_Plugin()
								  ->getCaptchaEnqueue()
								  ->getCaptchaHtml();
	}
}