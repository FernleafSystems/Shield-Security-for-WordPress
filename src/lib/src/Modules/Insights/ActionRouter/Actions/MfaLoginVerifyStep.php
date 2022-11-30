<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\LoginIntentRequestCapture;
use FernleafSystems\Wordpress\Services\Services;

class MfaLoginVerifyStep extends MfaBase {

	use Traits\AuthNotRequired;

	public const SLUG = 'wp_login_2fa_verify';

	protected function exec() {
		if ( Services::Request()->isPost() && !Services::WpUsers()->isUserLoggedIn() ) {
			$success = true;

			add_action( 'wp_loaded', function () {
				( new LoginIntentRequestCapture() )
					->setMod( $this->primary_mod )
					->runCapture();
				// TODO: move the render that's embedded in the capture.
			}, 8 ); // before rename login render
		}
		else {
			$success = false;
		}

		$this->response()->action_response_data = [
			'success' => $success,
		];
	}
}