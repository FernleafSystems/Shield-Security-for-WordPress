<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Captcha;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;
use FernleafSystems\Wordpress\Services\Services;

class CheckCaptchaSettings {

	use ModConsumer;

	public function checkAll() {
		$this->verifyProSettings();
		$this->verifyKeys();
	}

	public function verifyProSettings() {
		/** @var Plugin\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( !$this->getCon()->isPremiumActive() && $oOpts->getOpt( 'google_recaptcha_style' ) !== 'light' ) {
			$oOpts->setOpt( 'google_recaptcha_style', 'light' );
		}
	}

	public function verifyKeys() {
		/** @var \ICWP_WPSF_FeatureHandler_Plugin $oMod */
		$oMod = $this->getMod();
		/** @var Plugin\Options $oOpts */
		$oOpts = $this->getOptions();
		$oCfg = $oMod->getCaptchaCfg();

		$nAt = -1;
		if ( $oCfg->ready && $oOpts->getOpt( 'captcha_checked_at' ) <= 0 ) {
			if ( $oCfg->provider == 'grecaptcha' ) {
				$bValid = $this->verifyRecaptcha();
			}
			elseif ( $oCfg->provider == 'hcaptcha' ) {
				$bValid = $this->verifyHcaptcha();
			}
			else {
				$bValid = false;
			}
			$nAt = $bValid ? Services::Request()->ts() : 0;
		}
		$oOpts->setOpt( 'captcha_checked_at', $nAt );
	}

	/**
	 * @return bool
	 */
	private function verifyHcaptcha() {
		/** @var \ICWP_WPSF_FeatureHandler_Plugin $oMod */
		$oMod = $this->getMod();
		$oCfg = $oMod->getCaptchaCfg();
		return substr_count( $oCfg->key, '-' ) > 1
			   && strpos( $oCfg->secret, '0x' ) === 0;
	}

	/**
	 * @return bool
	 */
	private function verifyRecaptcha() {
		/** @var \ICWP_WPSF_FeatureHandler_Plugin $oMod */
		$oMod = $this->getMod();

		$sResponse = Services::HttpRequest()->getContent( add_query_arg(
			[
				'secret'   => $oMod->getCaptchaCfg()->secret,
				'response' => rand(),
			],
			'https://www.google.com/recaptcha/api/siteverify'
		) );

		$bValid = false;
		if ( !empty( $sResponse ) ) {
			$aDec = json_decode( $sResponse, true );
			$bValid = is_array( $aDec ) && is_array( $aDec[ 'error-codes' ] )
					  && !in_array( 'invalid-input-secret', $aDec[ 'error-codes' ] );
		}
		return $bValid;
	}
}
