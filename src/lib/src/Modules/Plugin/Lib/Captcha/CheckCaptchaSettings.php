<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Captcha;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class CheckCaptchaSettings {

	use ModConsumer;

	public function checkAll() {
		$this->verifyProSettings();
		$this->verifyKeys();
	}

	public function verifyProSettings() {
		if ( !self::con()->isPremiumActive() && $this->opts()->getOpt( 'google_recaptcha_style' ) !== 'light' ) {
			$this->opts()->setOpt( 'google_recaptcha_style', 'light' );
		}
	}

	public function verifyKeys() {
		$cfg = $this->mod()->getCaptchaCfg();

		$at = -1;
		if ( $cfg->ready && $this->opts()->getOpt( 'captcha_checked_at' ) <= 0 ) {
			if ( $cfg->provider == CaptchaConfigVO::PROV_GOOGLE_RECAP2 ) {
				$valid = $this->verifyRecaptcha();
			}
			elseif ( $cfg->provider == CaptchaConfigVO::PROV_HCAPTCHA ) {
				$valid = $this->verifyHcaptcha();
			}
			else {
				$valid = false;
			}
			$at = $valid ? Services::Request()->ts() : 0;
		}
		$this->opts()->setOpt( 'captcha_checked_at', $at );
	}

	private function verifyHcaptcha() :bool {
		$cfg = $this->mod()->getCaptchaCfg();
		return \substr_count( $cfg->key, '-' ) > 1 && \strpos( $cfg->secret, '0x' ) === 0;
	}

	private function verifyRecaptcha() :bool {
		$response = Services::HttpRequest()->getContent(
			URL::Build( 'https://www.google.com/recaptcha/api/siteverify', [
				'secret'   => $this->mod()->getCaptchaCfg()->secret,
				'response' => \rand(),
			] )
		);

		$valid = false;
		if ( !empty( $response ) ) {
			$dec = \json_decode( $response, true );
			$valid = \is_array( $dec )
					 && \is_array( $dec[ 'error-codes' ] )
					 && !\in_array( 'invalid-input-secret', $dec[ 'error-codes' ] );
		}
		return $valid;
	}
}
