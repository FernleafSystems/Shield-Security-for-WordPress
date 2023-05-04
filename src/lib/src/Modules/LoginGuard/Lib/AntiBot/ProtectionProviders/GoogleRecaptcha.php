<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\ProtectionProviders;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\ReCaptcha\TestRequest;

class GoogleRecaptcha extends BaseProtectionProvider {

	public function setup() {
		$this->con()
			 ->getModule_Plugin()
			 ->getCaptchaEnqueue()
			 ->setMod( $this->mod() )
			 ->setToEnqueue();
	}

	public function performCheck( $formProvider ) {
		if ( !$this->isFactorTested() ) {
			$this->setFactorTested( true );
			try {
				$this->getResponseTester()
					 ->setMod( $this->mod() )
					 ->test();
			}
			catch ( \Exception $e ) {
				$this->processFailure();
				throw $e;
			}
		}
	}

	/**
	 * @return TestRequest
	 */
	protected function getResponseTester() {
		return new TestRequest();
	}

	public function buildFormInsert( $formProvider ) :string {
		return $this->getCaptchaHtml();
	}

	private function getCaptchaHtml() :string {
		if ( $this->mod()->getCaptchaCfg()->invisible ) {
			$extraStyles = '';
		}
		else {
			$extraStyles = '<style>@media screen {#rc-imageselect, .icwpg-recaptcha iframe {transform:scale(0.895);-webkit-transform:scale(0.895);transform-origin:0 0;-webkit-transform-origin:0 0;}</style>';
		}
		return $extraStyles.$this->con()
								 ->getModule_Plugin()
								 ->getCaptchaEnqueue()
								 ->getCaptchaHtml();
	}
}