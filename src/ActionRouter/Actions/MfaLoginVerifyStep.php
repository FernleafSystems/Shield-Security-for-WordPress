<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\LoginIntentRequestCapture;
use FernleafSystems\Wordpress\Services\Services;

class MfaLoginVerifyStep extends BaseAction {

	use Traits\AuthNotRequired;

	public const SLUG = 'wp_login_2fa_verify';

	protected function exec() {
		if ( Services::Request()->isPost() && !Services::WpUsers()->isUserLoggedIn() ) {
			$success = true;
			add_action( 'wp_loaded', function () {
				( new LoginIntentRequestCapture() )->runCapture();
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