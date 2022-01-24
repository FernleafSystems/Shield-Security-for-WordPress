<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Utilities\Data\Response\StdResponse;
use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\WpLoginPageReplica;
use FernleafSystems\Wordpress\Services\Services;

class MfaController extends Shield\Modules\Base\Common\ExecOnceModConsumer {

	use Shield\Utilities\Consumer\WpLoginCapture;

	/**
	 * @var Provider\BaseProvider[]
	 */
	private $providers;

	/**
	 * @var LoginIntentPage
	 */
	private $loginIntentPageHandler;

	protected function run() {

		$this->setupLoginCaptureHooks();

		switch ( $this->getOptions()->getOpt( 'mfa_verify_page' ) ) {

			case 'wp_login':
				( new WpLoginPageReplica\WPLoginPageHandler() )
					->setMod( $this->getMod() )
					->execute();
				break;

			case 'custom_shield':
			default:
				add_action( 'init', [ $this, 'onWpInit' ] );
				break;
		}

		// Profile handling
		add_action( 'wp_loaded', [ $this, 'onWpLoaded' ] );
	}

	public function onWpInit() {
		$user = Services::WpUsers()->getCurrentWpUser();
		if ( $this->useLoginIntentPage() && $user instanceof \WP_User ) {
			$this->assessLoginIntent( $user );
		}
	}

	private function useLoginIntentPage() :bool {
		return $this->getOptions()->getOpt( 'mfa_verify_page', 'custom_shield' );
	}

	public function onWpLoaded() {
		( new MfaProfilesController() )->setMfaController( $this )->execute();
	}

	protected function captureLogin( \WP_User $user ) {
		$this->captureLoginIntent( $user );
	}

	private function captureLoginIntent( \WP_User $user ) {
		if ( $this->isSubjectToLoginIntent( $user )
			 && !Services::WpUsers()->isAppPasswordAuth() && !$this->canUserMfaSkip( $user ) ) {

			$providers = $this->getProvidersForUser( $user, true );
			if ( !empty( $providers ) ) {
				foreach ( $providers as $provider ) {
					$provider->setUser( $user )
							 ->captureLoginAttempt();
				}

				$meta = $this->getCon()->getUserMeta( $user );

				$intents = $meta->login_intents ?? [];
				$intents[ $this->getVisitorID() ] = Services::Request()->ts();
				$meta->login_intents = $this->filterExpiredIntents( $intents );
			}
		}
	}

	private function assessLoginIntent( \WP_User $user ) {
		if ( $this->hasLoginIntent( $user ) ) {

			if ( $this->isSubjectToLoginIntent( $user ) ) {

				if ( $this->getLoginIntentExpiresAt() > Services::Request()->ts() ) {

					$process = true;

					// by pass certain ajax request
					if ( Services::WpGeneral()->isAjax() ) {
						$req = Services::Request();
						if ( $req->post( 'action' ) === $this->getCon()->prefix()
							 && in_array( $req->post( 'exec' ), [ 'user_sms2fa_intentstart' ] ) ) {
							$process = false;
						}
					}

					if ( $process ) {
						$this->processActiveLoginIntent();
					}
				}
				else {
					$this->destroyLogin( $user );
					Services::Response()->redirectHere();
				}
			}
			else {
				// This handles the case where an admin changes a setting while a user is logged-in
				// So to prevent this, we remove any intent for a user that isn't subject to it right now
				$this->removeLoginIntent( $user );
			}
		}
	}

	private function getLoginIntentPageHandler() :LoginIntentPage {
		if ( !isset( $this->loginIntentPageHandler ) ) {
			$this->loginIntentPageHandler = ( new LoginIntentPage() )->setMfaController( $this );
		}
		return $this->loginIntentPageHandler;
	}

	/**
	 * @return Provider\BaseProvider[]
	 */
	public function getProviders() :array {
		if ( !is_array( $this->providers ) ) {
			$this->providers = array_map(
				function ( $provider ) {
					return $provider->setMod( $this->getMod() );
				},
				[
					Provider\Email::SLUG       => new Provider\Email(),
					Provider\GoogleAuth::SLUG  => new Provider\GoogleAuth(),
					Provider\Yubikey::SLUG     => new Provider\Yubikey(),
					Provider\BackupCodes::SLUG => new Provider\BackupCodes(),
					Provider\U2F::SLUG         => new Provider\U2F(),
				]
			);
		}
		return $this->providers;
	}

	/**
	 * Ensures that BackupCode provider isn't supplied on its own, and the user profile is setup for each.
	 * @return Provider\BaseProvider[]
	 */
	public function getProvidersForUser( \WP_User $user, bool $onlyActive = false ) :array {
		$Ps = array_filter(
			$this->getProviders(),
			function ( $provider ) use ( $user, $onlyActive ) {
				$provider->setUser( $user );
				return $provider->isProviderAvailableToUser()
					   && ( !$onlyActive || $provider->isProfileActive() );
			}
		);

		// BackupCode should NEVER be the only 1 provider available.
		if ( count( $Ps ) === 1 ) {
			/** @var Provider\BaseProvider $first */
			$first = reset( $Ps );
			if ( !$first::STANDALONE ) {
				$Ps = [];
			}
		}
		return $Ps;
	}

	/**
	 * hooked to 'init' and only run if a user is logged-in (not on the login request)
	 */
	private function processActiveLoginIntent() {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();
		$con = $this->getCon();
		$req = Services::Request();
		$WPResp = Services::Response();
		$WPUsers = Services::WpUsers();

		// Is 2FA/login-intent submit
		if ( $req->request( $this->getLoginIntentRequestFlag() ) == 1 ) {

			$user = $WPUsers->getCurrentWpUser();
			if ( $req->post( 'cancel' ) == 1 ) {
				$this->destroyLogin( $user );
				$redirect = $req->post( 'cancel_href' );
				empty( $redirect ) ? $WPResp->redirectToLogin() : $WPResp->redirect( $redirect );
			}

			try {
				( new ValidateLoginIntentRequest() )
					->setMfaController( $this )
					->run( $user );

				if ( $req->post( 'skip_mfa' ) === 'Y' ) {
					( new MfaSkip() )
						->setMod( $this->getMod() )
						->addMfaSkip( $user );
				}

				$con->fireEvent( '2fa_success' );

				$flash = __( 'Success', 'wp-simple-firewall' ).'! '.__( 'Thank you for authenticating your login.', 'wp-simple-firewall' );
				if ( $opts->isEnabledBackupCodes() ) {
					$flash .= ' '.__( 'If you used your Backup Code, you will need to reset it.', 'wp-simple-firewall' ); //TODO::
				}
				$this->getMod()->setFlashAdminNotice( $flash );

				$redirect = $req->post( 'redirect_to' );
				empty( $redirect ) ? $WPResp->redirectHere() : $WPResp->redirect( rawurldecode( $redirect ) );
			}
			catch ( \Exception $e ) {
				$con->getAdminNotices()
					->addFlash(
						__( 'One or more of your authentication codes failed or was missing.', 'wp-simple-firewall' ),
						true
					);
				// We don't protect against loops here to prevent bypassing of the login intent page.
				Services::Response()->redirect( Services::Request()->getUri(), [], true, false );
			}
		}
		elseif ( $opts->isUseLoginIntentPage() ) {
			$this->getLoginIntentPageHandler()->loadPage();
		}
		die();
	}

	public function canUserMfaSkip( \WP_User $user ) :bool {
		$canSkip = ( new MfaSkip() )
			->setMod( $this->getMod() )
			->canMfaSkip( $user );

//		if ( !$canSkip && $this->getCon()->isPremiumActive() && @class_exists( 'WC_Social_Login' ) ) {
//			// custom support for WooCommerce Social login
//			$meta = $this->getCon()->getUserMeta( $user );
//			$canSkip = $meta->wc_social_login_valid ?? false;
//		}

		return (bool)apply_filters( 'shield/2fa_skip',
			apply_filters( 'odp-shield-2fa_skip', $canSkip ) );
	}

	public function isSubjectToLoginIntent( \WP_User $user ) :bool {
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		return !$mod->isVisitorWhitelisted() && count( $this->getProvidersForUser( $user, true ) ) > 0;
	}

	public function removeAllFactorsForUser( int $userID ) :StdResponse {
		$result = new StdResponse();

		$user = Services::WpUsers()->getUserById( $userID );
		if ( $user instanceof \WP_User ) {
			foreach ( $this->getProvidersForUser( $user, true ) as $provider ) {
				$provider->setUser( $user )
						 ->remove();
			}
			$result->success = true;
			$result->msg_text = sprintf( __( 'All MFA providers removed from user with ID %s.' ),
				$userID );
		}
		else {
			$result->success = false;
			$result->error_text = sprintf( __( "User doesn't exist with ID %s." ),
				$userID );
		}

		return $result;
	}

	public function getLoginIntentExpiresAt() :int {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();
		$sessCon = $this->getCon()
						->getModule_Sessions()
						->getSessionCon();

		$expiresAt = 0;
		if ( $sessCon->hasSession() && $this->hasLoginIntent( Services::WpUsers()->getCurrentWpUser() ) ) {
			$expiresAt = Services::Request()
								 ->carbon()
								 ->setTimestamp( $sessCon->getCurrent()->logged_in_at )
								 ->addMinutes( $opts->getLoginIntentMinutes() )->timestamp;
		}
		return $expiresAt;
	}

	public function hasLoginIntent( \WP_User $user ) :bool {
		$meta = $this->getCon()->getUserMeta( $user );
		return !empty( $this->filterExpiredIntents( $meta->login_intents )[ $this->getVisitorID() ] );
	}

	private function getVisitorID() :string {
		return md5( Services::Request()->getUserAgent().Services::IP()->getRequestIp() );
	}

	/**
	 * Use this ONLY when the login intent has been successfully verified.
	 * @return $this
	 */
	public function removeLoginIntent( \WP_User $user ) {
		$meta = $this->getCon()->getUserMeta( $user );
		$intents = $meta->login_intents ?? [];
		unset( $intents[ $this->getVisitorID() ] );
		$meta->login_intents = $this->filterExpiredIntents( $intents );
		return $this;
	}

	private function getLoginIntentRequestFlag() :string {
		return $this->getCon()->prefix( 'login-intent-request' );
	}

	private function destroyLogin( \WP_User $user ) {
		$this->removeLoginIntent( $user );
		Services::WpUsers()->logoutUser();
	}

	private function filterExpiredIntents( array $intents ) :array {
		return array_filter(
			$intents,
			function ( $intentStart ) {
				/** @var LoginGuard\Options $opts */
				$opts = $this->getOptions();
				return is_int( $intentStart )
					   && $intentStart > ( Services::Request()->ts() - $opts->getLoginIntentMinutes()*60 );
			}
		);
	}
}