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
		/** @var Plugin\ModCon $mod */
		$mod = $this->getMod();
		/** @var Plugin\Options $oOpts */
		$oOpts = $this->getOptions();
		$oCfg = $mod->getCaptchaCfg();

		$nAt = -1;
		if ( $oCfg->ready && $oOpts->getOpt( 'captcha_checked_at' ) <= 0 ) {
			if ( $oCfg->provider == CaptchaConfigVO::PROV_GOOGLE_RECAP2 ) {
				$bValid = $this->verifyRecaptcha();
			}
			elseif ( $oCfg->provider == CaptchaConfigVO::PROV_HCAPTCHA ) {
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
		/** @var Plugin\ModCon $mod */
		$mod = $this->getMod();
		$oCfg = $mod->getCaptchaCfg();
		return substr_count( $oCfg->key, '-' ) > 1
			   && strpos( $oCfg->secret, '0x' ) === 0;
	}

	/**
	 * @return bool
	 */
	private function verifyRecaptcha() :bool {
		/** @var Plugin\ModCon $mod */
		$mod = $this->getMod();

		$sResponse = Services::HttpRequest()->getContent( add_query_arg(
			[
				'secret'   => $mod->getCaptchaCfg()->secret,
				'response' => rand(),
			],
			'https://www.google.com/recaptcha/api/siteverify'
		) );

		$valid = false;
		if ( !empty( $sResponse ) ) {
			$aDec = json_decode( $sResponse, true );
			$valid = is_array( $aDec ) && is_array( $aDec[ 'error-codes' ] )
					 && !in_array( 'invalid-input-secret', $aDec[ 'error-codes' ] );
		}
		return $valid;
	}
}
