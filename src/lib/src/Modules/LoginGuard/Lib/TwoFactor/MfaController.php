<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Session\Update;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;
use FernleafSystems\Wordpress\Services\Services;

class MfaController {

	use Shield\Modules\ModConsumer;

	/**
	 * @var Provider\BaseProvider[]
	 */
	private $aProviders;

	/**
	 * @var bool
	 */
	protected $bLoginAttemptCaptured;

	/**
	 * @var LoginIntentPage
	 */
	private $oLoginIntentPageHandler;

	public function run() {
		add_action( 'init', [ $this, 'onWpInit' ], 10, 2 );
		add_action( 'wp_login', [ $this, 'onWpLogin' ], 10, 2 );
		if ( !Services::WpUsers()->isProfilePage() ) { // This can be fired during profile update.
			add_action( 'set_logged_in_cookie', [ $this, 'onWpSetLoggedInCookie' ], 5, 4 );
		}
		add_action( 'wp_loaded', [ $this, 'onWpLoaded' ], 10, 2 );
	}

	public function onWpInit() {
		$this->assessLoginIntent();
	}

	/**
	 * @param string   $sUsername
	 * @param \WP_User $oUser
	 */
	public function onWpLogin( $sUsername, $oUser ) {
		$this->captureLoginIntent( $oUser );
	}

	public function onWpLoaded() {
		( new UserProfile() )
			->setMfaController( $this )
			->run();

		add_shortcode( 'SHIELD_2FA_LOGIN', function () {
			return $this->getLoginIntentPageHandler()->renderForm();
		} );
	}

	/**
	 * @param string $sCookie
	 * @param int    $nExpire
	 * @param int    $nExpiration
	 * @param int    $nUserId
	 */
	public function onWpSetLoggedInCookie( $sCookie, $nExpire, $nExpiration, $nUserId ) {
		$this->captureLoginIntent( Services::WpUsers()->getUserById( $nUserId ) );
	}

	/**
	 * @param \WP_User $oUser
	 */
	private function captureLoginIntent( $oUser ) {
		if ( empty( $this->bLoginAttemptCaptured ) && $oUser instanceof \WP_User ) {
			$this->bLoginAttemptCaptured = true;

			/** @var LoginGuard\Options $oOpts */
			$oOpts = $this->getOptions();
			if ( $this->isSubjectToLoginIntent( $oUser ) && !$this->canUserMfaSkip( $oUser ) ) {

				$aProviders = $this->getProvidersForUser( $oUser );
				if ( !empty( $aProviders ) ) {
					foreach ( $aProviders as $oProvider ) {
						$oProvider->captureLoginAttempt( $oUser );
					}

					$nTimeout = (int)apply_filters(
						$this->getCon()->prefix( 'login_intent_timeout' ),
						$oOpts->getDef( 'login_intent_timeout' )
					);
					$this->setLoginIntentExpiresAt(
						Services::Request()->carbon()->addMinutes( $nTimeout )->timestamp
					);
				}
			}
		}
	}

	/**
	 * Deals with the scenario when the user session has a login intent.
	 */
	private function assessLoginIntent() {
		$oUser = Services::WpUsers()->getCurrentWpUser();
		if ( $oUser instanceof \WP_User && $this->hasLoginIntent() ) {

			if ( $this->isSubjectToLoginIntent( $oUser ) ) {

				if ( $this->getLoginIntentExpiresAt() > Services::Request()->ts() ) {
					$this->processActiveLoginIntent();
				}
				else {
					Services::WpUsers()->logoutUser(); // clears the login and login intent
					Services::Response()->redirectHere();
				}
			}
			else {
				// This handles the case where an admin changes a setting while a user is logged-in
				// So to prevent this, we remove any intent for a user that isn't subject to it right now
				$this->removeLoginIntent();
			}
		}
	}

	/**
	 * @return LoginIntentPage
	 */
	private function getLoginIntentPageHandler() {
		if ( !isset( $this->oLoginIntentPageHandler ) ) {
			$this->oLoginIntentPageHandler = ( new LoginIntentPage() )->setMfaController( $this );
		}
		return $this->oLoginIntentPageHandler;
	}

	/**
	 * @return Provider\BaseProvider[]
	 */
	public function getProviders() {
		if ( !is_array( $this->aProviders ) ) {
			$this->aProviders = [
				Provider\Email::SLUG      => ( new Provider\Email() )->setMod( $this->getMod() ),
				Provider\GoogleAuth::SLUG => ( new Provider\GoogleAuth() )->setMod( $this->getMod() ),
				Provider\Yubikey::SLUG    => ( new Provider\Yubikey() )->setMod( $this->getMod() ),
				Provider\Backup::SLUG     => ( new Provider\Backup() )->setMod( $this->getMod() ),
			];
		}
		return $this->aProviders;
	}

	/**
	 * Ensures that BackupCode provider isn't supplied on its own, and the user profile is setup for each.
	 * @param \WP_User $oUser
	 * @param bool     $bOnlyActiveProfiles
	 * @return Provider\BaseProvider[]
	 */
	public function getProvidersForUser( $oUser, $bOnlyActiveProfiles = false ) {
		$aProviders = array_filter( $this->getProviders(),
			function ( $oProvider ) use ( $oUser, $bOnlyActiveProfiles ) {
				/** @var Provider\BaseProvider $oProvider */
				return $oProvider->isProviderAvailableToUser( $oUser )
					   && ( !$bOnlyActiveProfiles || $oProvider->isProfileActive( $oUser ) );
			}
		);
		// Backups should NEVER be the only 1 available.
		if ( count( $aProviders ) === 1 && isset( $aProviders[ Provider\Backup::SLUG ] ) ) {
			unset( $aProviders[ Provider\Backup::SLUG ] );
		}
		return $aProviders;
	}

	/**
	 * hooked to 'init' and only run if a user is logged-in (not on the login request)
	 */
	private function processActiveLoginIntent() {
		/** @var LoginGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		$oCon = $this->getCon();
		$oReq = Services::Request();
		$oWpResp = Services::Response();
		$oUser = Services::WpUsers()->getCurrentWpUser();

		// Is 2FA/login-intent submit
		if ( $oReq->request( $this->getLoginIntentRequestFlag() ) == 1 ) {

			if ( $oReq->post( 'cancel' ) == 1 ) {
				Services::WpUsers()->logoutUser(); // clears the login and login intent
				$sRedirectHref = $oReq->post( 'cancel_href' );
				empty( $sRedirectHref ) ? $oWpResp->redirectToLogin() : $oWpResp->redirect( $sRedirectHref );
			}
			elseif ( $this->validateLoginIntentRequest() ) {

				if ( $oReq->post( 'skip_mfa' ) === 'Y' ) { // store the browser hash
					$oCon->getUserMeta( $oUser )
						 ->addMfaSkipAgent( $oReq->getUserAgent(), $oOpts->getMfaSkip() );
				}
				$oCon->fireEvent( '2fa_success' );

				$sFlash = __( 'Success', 'wp-simple-firewall' ).'! '.__( 'Thank you for authenticating your login.', 'wp-simple-firewall' );
				if ( $oOpts->isEnabledBackupCodes() ) {
					$sFlash .= ' '.__( 'If you used your Backup Code, you will need to reset it.', 'wp-simple-firewall' ); //TODO::
				}
				$this->getMod()->setFlashAdminNotice( $sFlash );

				$this->removeLoginIntent();

				$sRedirectHref = $oReq->post( 'redirect_to' );
				empty( $sRedirectHref ) ? $oWpResp->redirectHere() : $oWpResp->redirect( rawurldecode( $sRedirectHref ) );
			}
			else {
				$oCon->getAdminNotices()
					 ->addFlash(
						 __( 'One or more of your authentication codes failed or was missing.', 'wp-simple-firewall' ),
						 true
					 );
				// We don't protect against loops here to prevent by-passing of the login intent page.
				Services::Response()->redirect( Services::Request()->getUri(), [], true, false );
			}
		}
		elseif ( $oOpts->isUseLoginIntentPage() ) {
			$this->getLoginIntentPageHandler()->loadPage();
		}
		die();
	}

	/**
	 * assume that a user is logged in.
	 * @return bool
	 */
	private function validateLoginIntentRequest() {
		try {
			$bValid = ( new ValidateLoginIntentRequest() )
				->setMfaController( $this )
				->run();
		}
		catch ( \Exception $oE ) {
			$bValid = true;
		}
		return $bValid;
	}

	/**
	 * @param \WP_User $oUser
	 * @return bool
	 */
	private function canUserMfaSkip( $oUser ) {
		/** @var LoginGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		$oReq = Services::Request();

		$oMeta = $this->getCon()->getUserMeta( $oUser );
		if ( $oOpts->isMfaSkip() ) {
			$aHashes = is_array( $oMeta->hash_loginmfa ) ? $oMeta->hash_loginmfa : [];
			$sAgentHash = md5( $oReq->getUserAgent() );
			$bCanSkip = isset( $aHashes[ $sAgentHash ] )
						&& ( (int)$aHashes[ $sAgentHash ] + $oOpts->getMfaSkip() ) > $oReq->ts();
		}
		elseif ( $this->getCon()->isPremiumActive() && @class_exists( 'WC_Social_Login' ) ) {
			// custom support for WooCommerce Social login
			$oMeta = $this->getCon()->getUserMeta( $oUser );
			$bCanSkip = isset( $oMeta->wc_social_login_valid ) ? $oMeta->wc_social_login_valid : false;
		}
		else {
			/**
			 * TODO: remove the HTTP_REFERER bit once iCWP plugin is updated.
			 * We want logins from iCWP to skip 2FA. To achieve this, iCWP plugin needs
			 * to add a TRUE filter on 'odp-shield-2fa_skip' at the point of login.
			 * Until then, we'll use the HTTP referrer as an indicator
			 */
			$bCanSkip = apply_filters(
				'odp-shield-2fa_skip',
				strpos( $oReq->server( 'HTTP_REFERER' ), 'https://app.icontrolwp.com/' ) === 0
			);
		}
		return $bCanSkip;
	}

	/**
	 * @param \WP_User $oUser
	 * @return bool
	 */
	private function isSubjectToLoginIntent( $oUser ) {
		return count( $this->getProvidersForUser( $oUser, true ) ) > 0;
	}

	/**
	 * @return int
	 */
	private function getLoginIntentExpiresAt() {
		return $this->getMod()->hasSession() ? $this->getMod()->getSession()->login_intent_expires_at : 0;
	}

	/**
	 * @return bool
	 */
	protected function hasLoginIntent() {
		return $this->getLoginIntentExpiresAt() > 0;
	}

	/**
	 * Use this ONLY when the login intent has been successfully verified.
	 * @return $this
	 */
	private function removeLoginIntent() {
		return $this->setLoginIntentExpiresAt( 0 );
	}

	/**
	 * @param int $nExpirationTime
	 * @return $this
	 */
	protected function setLoginIntentExpiresAt( $nExpirationTime ) {
		/** @var \ICWP_WPSF_FeatureHandler_LoginProtect $oMod */
		$oMod = $this->getMod();
		if ( $oMod->hasSession() ) {
			/** @var Update $oUpd */
			$oUpd = $oMod->getDbHandler_Sessions()->getQueryUpdater();
			$oUpd->updateLoginIntentExpiresAt( $oMod->getSession(), $nExpirationTime );
		}
		return $this;
	}

	/**
	 * @return string
	 */
	private function getLoginIntentRequestFlag() {
		return $this->getCon()->prefix( 'login-intent-request' );
	}
}