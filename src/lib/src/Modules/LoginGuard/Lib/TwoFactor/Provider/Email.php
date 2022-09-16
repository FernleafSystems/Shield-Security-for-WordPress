<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\MfaEmailSendIntent;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\MfaEmailToggle;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\SureSend\SendEmail;
use FernleafSystems\Wordpress\Services\Services;

class Email extends BaseProvider {

	const SLUG = 'email';

	public function getJavascriptVars() :array {
		return [
			'ajax' => [
				'profile_email2fa_toggle' => ActionData::Build( MfaEmailToggle::SLUG ),
			],
		];
	}

	/**
	 * If login nonce is provided, the OTP check is stricter and must be the same as that assigned to the nonce.
	 * Otherwise, we just check whether the OTP exists.
	 */
	protected function processOtp( string $otp ) :bool {
		$secret = $this->getSecret()[ $this->workingHashedLoginNonce ] ?? '';
		return !empty( $secret ) && wp_check_password( $otp, $secret );
	}

	/**
	 * @inheritDoc
	 */
	public function postSuccessActions() {
		parent::postSuccessActions();
		return $this->setSecret( [] );
	}

	public function getFormField() :array {
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		return [
			'slug'        => static::SLUG,
			'name'        => $this->getLoginFormParameter(),
			'type'        => 'text',
			'value'       => $this->fetchCodeFromRequest(),
			'placeholder' => __( 'A1B2C3', 'wp-simple-firewall' ),
			'text'        => __( 'Email OTP', 'wp-simple-firewall' ),
			'description' => __( 'Enter code sent to your email', 'wp-simple-firewall' ),
			'help_link'   => 'https://shsec.io/3t',
			'size'        => 6,
			'datas'       => [
				'auto_send'              => $mod->getMfaController()->isAutoSend2faEmail( $this->getUser() ) ? 1 : 0,
				'ajax_intent_email_send' => ActionData::BuildJson( MfaEmailSendIntent::SLUG ),
			],
			'supp'        => [
				'send_email' => __( 'Send OTP Code', 'wp-simple-firewall' ),
			]
		];
	}

	public function hasValidatedProfile() :bool {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();
		$validated = parent::hasValidatedProfile();
		$this->setProfileValidated(
			$this->isEnforced() || ( $validated && $opts->isEnabledEmailAuthAnyUserSet() )
		);
		return parent::hasValidatedProfile();
	}

	protected function isEnforced() :bool {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();
		return count( array_intersect( $opts->getEmail2FaRoles(), $this->getUser()->roles ) ) > 0;
	}

	protected function hasValidSecret() :bool {
		return true;
	}

	public function sendEmailTwoFactorVerify( string $plainNonce ) :bool {
		$con = $this->getCon();
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		$mfaCon = $mod->getMfaController();
		$user = $this->getUser();
		$userMeta = $con->getUserMeta( $user );
		$sureCon = $con->getModule_Comms()->getSureSendController();
		$useSureSend = $sureCon->isEnabled2Fa() && $sureCon->canUserSend( $user );

		$success = false;
		try {
			if ( !$mfaCon->verifyLoginNonce( $user, $plainNonce ) ) {
				throw new \Exception( 'No such login intent' );
			}

			$hashedNonce = $mfaCon->findHashedNonce( $user, $plainNonce );
			$intents = $mfaCon->getActiveLoginIntents( $user );
			$intents[ $hashedNonce ][ 'auto_email_sent' ] = true;
			$userMeta->login_intents = $intents;

			$otp = $this->generate2faCode( $hashedNonce );

			$success = ( $useSureSend && $this->send2faEmailSureSend( $otp ) )
					   || $this->getMod()
							   ->getEmailProcessor()
							   ->sendEmailWithTemplate(
								   '/email/lp_2fa_email_code.twig',
								   $user->user_email,
								   __( 'Two-Factor Login Verification', 'wp-simple-firewall' ),
								   [
									   'flags'   => [
										   'show_login_link' => !$this->getCon()->isRelabelled()
									   ],
									   'vars'    => [
										   'code' => $otp
									   ],
									   'hrefs'   => [
										   'login_link' => 'https://shsec.io/96',
									   ],
									   'strings' => [
										   'someone'          => __( 'Someone attempted to login into this WordPress site using your account.', 'wp-simple-firewall' ),
										   'requires'         => __( 'Login requires verification with the following code.', 'wp-simple-firewall' ),
										   'verification'     => __( 'Verification Code', 'wp-simple-firewall' ),
										   'login_link'       => __( 'Why no login link?', 'wp-simple-firewall' ),
										   'details_heading'  => __( 'Login Details', 'wp-simple-firewall' ),
										   'details_url'      => sprintf( '%s: %s', __( 'URL', 'wp-simple-firewall' ),
											   Services::WpGeneral()->getHomeUrl() ),
										   'details_username' => sprintf( '%s: %s', __( 'Username', 'wp-simple-firewall' ), $user->user_login ),
										   'details_ip'       => sprintf( '%s: %s', __( 'IP Address', 'wp-simple-firewall' ),
											   $this->getCon()->this_req->ip ),
									   ]
								   ]
							   );
		}
		catch ( \Exception $e ) {
		}

		return $success;
	}

	private function send2faEmailSureSend( string $code ) :bool {
		return ( new SendEmail() )
			->setMod( $this->getMod() )
			->send2FA( $this->getUser(), $code );
	}

	protected function getProviderSpecificRenderData() :array {
		return [
			'strings' => [
				'label_email_authentication'                => __( 'Email Authentication', 'wp-simple-firewall' ),
				'title'                                     => __( 'Email Authentication', 'wp-simple-firewall' ),
				'description_email_authentication_checkbox' => __( 'Toggle the option to enable/disable email-based login authentication.', 'wp-simple-firewall' ),
				'provided_by'                               => sprintf( __( 'Provided by %s', 'wp-simple-firewall' ),
					$this->getCon()->getHumanName() )
			]
		];
	}

	public function isProviderEnabled() :bool {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();
		return $opts->isEmailAuthenticationActive();
	}

	public function isProviderAvailableToUser() :bool {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();
		return parent::isProviderAvailableToUser()
			   && ( $this->isEnforced() || $opts->isEnabledEmailAuthAnyUserSet() );
	}

	private function generate2faCode( string $hashedLoginNonce ) :string {
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();

		$secrets = $this->getSecret();
		if ( !is_array( $secrets ) ) {
			$secrets = [];
		}

		$otp = $this->generateSimpleOTP();
		$secrets[ $hashedLoginNonce ] = wp_hash_password( $otp );

		// Clean old secrets linked to expired login intents
		$this->setSecret( array_intersect_key(
			$secrets,
			$mod->getMfaController()->getActiveLoginIntents( $this->getUser() )
		) );
		return $otp;
	}

	public function getProviderName() :string {
		return 'Email';
	}
}