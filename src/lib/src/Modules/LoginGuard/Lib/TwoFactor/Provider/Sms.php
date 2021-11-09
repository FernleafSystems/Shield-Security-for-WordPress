<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Sms\GetAvailableCountries;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\SureSend\SendEmail;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\SureSend\SendSms;
use FernleafSystems\Wordpress\Services\Services;

class Sms extends BaseProvider {

	const SLUG = 'sms';

	private $secretToDelete = '';

	public function captureLoginAttempt( \WP_User $user ) {
	}

	public function getJavascriptVars() :array {
		return [
			'ajax' => [
				'user_sms2fa_add' => $this->getMod()->getAjaxActionData( 'user_sms2fa_add' ),
			],
		];
	}

	public function addProvisionalRegistration( \WP_User $user, string $country, string $phone ) :string {
		$meta = $this->getCon()->getUserMeta( $user );
		$reg = is_array( $meta->sms_registration ) ? $meta->sms_registration : [];

		if ( @$reg[ 'country' ] === $country && @$reg[ 'phone' ] === $phone
			 && ( $reg[ 'verified' ] ?? false ) ) {
			throw new \Exception( 'This Phone number is already added and verified' );
		}

		$meta->sms_registration = [
			'country'  => $country,
			'phone'    => $phone,
			'code'     => (string)wp_rand( 100000, 999999 ),
			'verified' => false,
		];

		( new SendSms() )
			->setMod( $this->getMod() )
			->send2FA( $user, $meta->sms_registration[ 'code' ] );

		return $meta->sms_registration[ 'code' ];
	}

	/**
	 * @return $this
	 */
	public function postSuccessActions( \WP_User $user ) {
		if ( !empty( $this->secretToDelete ) ) {
			$secrets = $this->getAllCodes( $user );
			unset( $secrets[ $this->secretToDelete ] );
			$this->storeCodes( $user, $secrets );
		}
		return $this;
	}

	protected function processOtp( \WP_User $user, string $otp ) :bool {
		$valid = false;
		foreach ( $this->getAllCodes( $user ) as $secret => $expiresAt ) {
			if ( wp_check_password( $otp, $secret ) ) {
				$valid = true;
				$this->secretToDelete = $secret;
				break;
			}
		}
		return $valid;
	}

	public function getFormField() :array {
		return [
			'name'        => $this->getLoginFormParameter(),
			'type'        => 'text',
			'value'       => $this->fetchCodeFromRequest(),
			'placeholder' => __( 'This code was just sent to your registered Phone number.', 'wp-simple-firewall' ),
			'text'        => __( 'SMS OTP', 'wp-simple-firewall' ),
			'help_link'   => 'https://shsec.io/3t'
		];
	}

	/**
	 * @inheritDoc
	 */
	public function handleUserProfileSubmit( \WP_User $user ) {

		$bWasEnabled = $this->isProfileActive( $user );
		$bToEnable = Services::Request()->post( 'shield_enable_mfaemail' ) === 'Y';

		$msg = null;
		$error = false;
		if ( $bToEnable ) {
			$this->setProfileValidated( $user );
			if ( !$bWasEnabled ) {
				$msg = __( 'Email Two-Factor Authentication has been enabled.', 'wp-simple-firewall' );
			}
		}
		elseif ( $this->isEnforced( $user ) ) {
			$msg = __( "Email Two-Factor Authentication couldn't be disabled because it is enforced based on your user roles.", 'wp-simple-firewall' );
			$error = true;
		}
		else {
			$this->setProfileValidated( $user, false );
			$msg = __( 'Email Two-Factor Authentication has been disabled.', 'wp-simple-firewall' );
		}

		if ( !empty( $msg ) ) {
			$this->getMod()->setFlashAdminNotice( $msg, $error );
		}
	}

	/**
	 * @param string $secret
	 * @return bool
	 */
	protected function isSecretValid( $secret ) {
		return true;
	}

	/**
	 * @return $this
	 */
	private function sendSmsTwoFactorVerify( \WP_User $user ) {
		$sureCon = $this->getCon()->getModule_Comms()->getSureSendController();
		$useSureSend = $sureCon->isEnabled2Fa() && $sureCon->canUserSend( $user );

		try {
			$code = $this->genNewCode( $user );

			$sendSuccess = ( $useSureSend && $this->send2faEmailSureSend( $user, $code ) )
						   || $this->getMod()
								   ->getEmailProcessor()
								   ->sendEmailWithTemplate(
									   '/email/lp_2fa_email_code',
									   $user->user_email,
									   __( 'Two-Factor Login Verification', 'wp-simple-firewall' ),
									   [
										   'flags'   => [
											   'show_login_link' => !$this->getCon()->isRelabelled()
										   ],
										   'vars'    => [
											   'code' => $code
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
												   Services::IP()->getRequestIp() ),
										   ]
									   ]
								   );
		}
		catch ( \Exception $e ) {
			$sendSuccess = false;
		}

		return $this;
	}

	private function send2faEmailSureSend( \WP_User $user, string $code ) :bool {
		return ( new SendEmail() )
			->setMod( $this->getMod() )
			->send2FA(
				$user,
				$code
			);
	}

	protected function getProviderSpecificRenderData( \WP_User $user ) :array {
		$countries = ( new GetAvailableCountries() )
			->setMod( $this->getMod() )
			->run();
		return [
			'flags'   => [
				'has_countries' => !empty( $countries )
			],
			'strings' => [
				'label_email_authentication'  => __( 'SMS Authentication', 'wp-simple-firewall' ),
				'title'                       => __( 'SMS Authentication', 'wp-simple-firewall' ),
				'provide_full_phone_number'   => __( 'Provide Your Full Mobile Telephone Number', 'wp-simple-firewall' ),
				'description_sms_auth_submit' => __( 'Click to verify your mobile number', 'wp-simple-firewall' ),
				'provided_by'                 => sprintf( __( 'Provided by %s', 'wp-simple-firewall' ),
					$this->getCon()->getHumanName() )
			],
			'vars'    => [
				'countries' => $countries
			]
		];
	}

	public function isProviderEnabled() :bool {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();
		return $opts->isEnabledSmsAuth();
	}

	private function genLoginLink( \WP_User $user, string $otp ) :string {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();
		$action = uniqid( '2fa_verify' );
		return add_query_arg(
			[
				'user'                         => $user->user_login,
				$this->getLoginFormParameter() => $otp,
				'shield_nonce_action'          => $action,
				'shield_nonce'                 => $this->getCon()
					->nonce_handler->create( $action, $opts->getLoginIntentMinutes()*60 ),
			],
			Services::WpGeneral()->getHomeUrl()
		);
	}

	/**
	 * @param \WP_User $user
	 * @return string
	 */
	private function genNewCode( \WP_User $user ) {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();

		$secrets = $this->getAllCodes( $user );
		$new = substr( strtoupper( preg_replace( '#io#i', '', wp_generate_password( 30, false ) ) ), 0, 6 );
		$secrets[ wp_hash_password( $new ) ] = Services::Request()
													   ->carbon()
													   ->addMinutes( $opts->getLoginIntentMinutes() )->timestamp;

		$this->storeCodes( $user, array_slice( $secrets, -10 ) );
		return $new;
	}

	/**
	 * @param \WP_User $user
	 * @return array
	 */
	private function getAllCodes( \WP_User $user ) {
		$secrets = $this->getSecret( $user );
		return array_filter(
			is_array( $secrets ) ? $secrets : [],
			function ( $ts ) {
				return $ts >= Services::Request()->ts();
			}
		);
	}

	/**
	 * @param \WP_User $user
	 * @param array    $codes
	 * @return $this
	 */
	private function storeCodes( \WP_User $user, array $codes ) {
		return $this->setSecret( $user, $codes );
	}

	public function getProviderName() :string {
		return 'Email';
	}
}