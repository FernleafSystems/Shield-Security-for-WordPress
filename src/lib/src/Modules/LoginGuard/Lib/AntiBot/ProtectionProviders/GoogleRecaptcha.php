<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\ProtectionProviders;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\ReCaptcha\TestRequest;

class GoogleRecaptcha extends BaseProtectionProvider {

	public function setup() {
		$this->getCon()
			 ->getModule_Plugin()
			 ->getCaptchaEnqueue()
			 ->setMod( $this->getMod() )
			 ->setToEnqueue();
	}

	public function performCheck( $formProvider ) {
		if ( !$this->isFactorTested() ) {
			$this->setFactorTested( true );
			try {
				$this->getResponseTester()
					 ->setMod( $this->getMod() )
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
		/** @var ModCon $mod */
		$mod = $this->getMod();
		if ( $mod->getCaptchaCfg()->invisible ) {
			$extraStyles = '';
		}
		else {
			$extraStyles = '<style>@media screen {#rc-imageselect, .icwpg-recaptcha iframe {transform:scale(0.895);-webkit-transform:scale(0.895);transform-origin:0 0;-webkit-transform-origin:0 0;}</style>';
		}
		return $extraStyles.$this->getCon()
								 ->getModule_Plugin()
								 ->getCaptchaEnqueue()
								 ->getCaptchaHtml();
	}
}