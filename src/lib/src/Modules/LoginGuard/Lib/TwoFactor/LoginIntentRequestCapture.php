<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\{
	Actions\FullPageDisplay\StandardFullPageDisplay,
	Actions\Render\FullPage\Mfa\ShieldLoginIntentPage,
	Actions\Render\FullPage\Mfa\WpReplicaLoginIntentPage,
	Exceptions\ActionException
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Exceptions\{
	CouldNotValidate2FA,
	InvalidLoginIntentException,
	LoginCancelException,
	NoActiveProvidersForUserException,
	NotValidUserException,
	OtpVerificationFailedException,
	TooManyAttemptsException
};
use FernleafSystems\Wordpress\Services\Services;

class LoginIntentRequestCapture extends Shield\Modules\Base\Common\ExecOnceModConsumer {

	/**
	 * @var \WP_User
	 */
	private $user;

	protected function canRun() :bool {
		return false;
	}

	public function runCapture() {
		$con = $this->getCon();
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		$mfaCon = $mod->getMfaController();
		$req = Services::Request();

		try {
			$user = $req->post( 'wp_user_id' ) ? Services::WpUsers()->getUserById( $req->post( 'wp_user_id' ) ) : null;
			if ( empty( $user ) ) {
				throw new NotValidUserException();
			}
			$this->user = $user;
			$this->capture();
		}
		catch ( NotValidUserException $e ) {
			// output error about no login intent so there's no way to discern externally whether such a user exists
			Services::Response()->redirectToLogin( [
				'shield_msg' => 'no_user_login_intent'
			] );
		}
		catch ( InvalidLoginIntentException $e ) {
			Services::Response()->redirectToLogin( [
				'shield_msg' => 'no_user_login_intent'
			] );
		}
		catch ( LoginCancelException $e ) {
			// This should always be a user since we can only throw this exception after loading the user
			$con->getUserMeta( $this->user )->login_intents = [];
			$redirect = $req->post( 'cancel_href' );
			empty( $redirect ) ? Services::Response()->redirectToLogin() : Services::Response()->redirect( $redirect );
		}
		catch ( TooManyAttemptsException $e ) {
			$con->getUserMeta( $this->user )->login_intents = [];
			Services::Response()->redirectToLogin( [
				'shield_msg' => 'too_many_attempts'
			] );
		}
		catch ( NoActiveProvidersForUserException $e ) {
			$con->getUserMeta( $this->user )->login_intents = [];
			Services::Response()->redirectToLogin( [
				'shield_msg' => 'no_providers'
			] );
		}
		catch ( OtpVerificationFailedException | CouldNotValidate2FA $e ) {
			// Allow a further attempt to 2FA
			try {
				$con->getModule_Insights()
					->getActionRouter()
					->action( StandardFullPageDisplay::SLUG, [
						'render_slug' => $mfaCon->useLoginIntentPage() ? ShieldLoginIntentPage::SLUG : WpReplicaLoginIntentPage::SLUG,
						'render_data' => [
							'user_id'           => $this->user->ID,
							'include_body'      => true,
							'plain_login_nonce' => $req->request( 'login_nonce', false, '' ),
							'interim_login'     => $req->request( 'interim-login', false, '' ),
							'redirect_to'       => $req->request( 'redirect_to', false, '' ),
							'rememberme'        => $req->request( 'rememberme', false, '' ),
							'msg_error'         => __( 'Could not verify your 2FA codes', 'wp-simple-firewall' ),
						],
					] );
			}
			catch ( ActionException $e ) {
				die( $e->getMessage() );
			}
		}
	}

	/**
	 * @throws CouldNotValidate2FA
	 * @throws Exceptions\TooManyAttemptsException
	 * @throws LoginCancelException
	 * @throws NoActiveProvidersForUserException
	 * @throws OtpVerificationFailedException
	 * @throws InvalidLoginIntentException
	 */
	private function capture() {
		$con = $this->getCon();
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();
		$req = Services::Request();

		$valid = ( new LoginIntentRequestValidate() )
			->setMod( $mod )
			->setWpUser( $this->user )
			->run( (string)$req->post( 'login_nonce' ), $req->post( 'cancel' ) == '1' );

		if ( $valid ) {
			wp_set_auth_cookie( $this->user->ID, (bool)$req->post( 'rememberme' ) );

			if ( $req->post( 'skip_mfa' ) === 'Y' ) {
				( new MfaSkip() )
					->setMod( $this->getMod() )
					->addMfaSkip( $this->user );
			}

			$con->fireEvent( '2fa_success' );

			global $interim_login;
			$interim_login = (bool)$req->request( 'interim-login' );
			if ( $interim_login ) {
				add_filter( 'login_message', function () {
					return '';
				}, 100, 0 );

				$con->getModule_Insights()
					->getActionRouter()
					->action( StandardFullPageDisplay::SLUG, [
						'render_slug' => WpReplicaLoginIntentPage::SLUG,
						'render_data' => [
							'user_id'           => $this->user->ID,
							'include_body'      => false,
							'interim_message'   => __( '2FA authentication verified successfully.', 'wp-simple-firewall' ),
							'plain_login_nonce' => $req->request( 'login_nonce', false, '' ),
							'interim_login'     => $req->request( 'interim-login', false, '' ),
							'redirect_to'       => $req->request( 'redirect_to', false, '' ),
							'rememberme'        => $req->request( 'rememberme', false, '' ),
						],
					] );
			}
			else {
				$flash = __( 'Success', 'wp-simple-firewall' ).'! '.__( 'Thank you for authenticating your login.', 'wp-simple-firewall' );
				if ( $opts->isEnabledBackupCodes() ) {
					$flash .= ' '.__( 'If you used your Backup Code, you will need to reset it.', 'wp-simple-firewall' );
				}
				$con->getAdminNotices()
					->addFlash(
						sprintf( '[%s] %s', $this->getCon()->getHumanName(), $flash ),
						$this->user
					);
			}

			$redirect = $req->request( 'redirect_to', false, $req->getPath() );
			Services::Response()->redirect(
				apply_filters( 'login_redirect', $redirect, $redirect, $this->user ),
				[], true, false
			);
		}
	}
}