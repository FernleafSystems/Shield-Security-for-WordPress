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
				'user_sms2fa_add'    => $this->getMod()->getAjaxActionData( 'user_sms2fa_add' ),
				'user_sms2fa_verify' => $this->getMod()->getAjaxActionData( 'user_sms2fa_verify' ),
				'user_sms2fa_remove' => $this->getMod()->getAjaxActionData( 'user_sms2fa_remove' ),
			],
		];
	}

	/**
	 * @throws \Exception
	 */
	public function verifyProvisionalRegistration( \WP_User $user, string $country, string $phone, string $code ) :bool {
		$meta = $this->getCon()->getUserMeta( $user );
		$reg = is_array( $meta->sms_registration ) ? $meta->sms_registration : [];

		if ( @$reg[ 'country' ] === $country && @$reg[ 'phone' ] === $phone
			 && ( $reg[ 'verified' ] ?? false ) ) {
			throw new \Exception( 'This Phone number is already added and verified' );
		}
		if ( empty( $reg[ 'code' ] ) ) {
			throw new \Exception( "The verification code couldn't be verified because the profile wasn't ready." );
		}
		if ( $reg[ 'code' ] !== trim( strtoupper( $code ) ) ) {
			throw new \Exception( "The verification code provided wasn't correct." );
		}

		$meta->sms_registration = [
			'country'  => $country,
			'phone'    => $phone,
			'verified' => true,
		];

		$this->setProfileValidated( $user );

		return true;
	}

	/**
	 * @throws \Exception
	 */
	public function addProvisionalRegistration( \WP_User $user, string $country, string $phone ) :string {
		$meta = $this->getCon()->getUserMeta( $user );
		$reg = is_array( $meta->sms_registration ) ? $meta->sms_registration : [];

		if ( @$reg[ 'country' ] === $country && @$reg[ 'phone' ] === $phone
			 && ( $reg[ 'verified' ] ?? false ) ) {
			throw new \Exception( 'This Phone number is already added and verified' );
		}

		$this->setProfileValidated( $user, false );

		$meta->sms_registration = [
			'country'  => $country,
			'phone'    => $phone,
			'code'     => $this->generateSimpleOTP(),
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
	 * @param string $secret
	 * @return bool
	 */
	protected function isSecretValid( $secret ) {
		return true;
	}

	public function remove( \WP_User $user ) {
		$this->getCon()->getUserMeta( $user )->sms_registration = [];
		parent::remove( $user );
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

		$validatedNumber = '';
		if ( $this->hasValidatedProfile( $user ) ) {
			$smsReg = $this->getCon()->getUserMeta( $user )->sms_registration;
			$validatedNumber = sprintf( '[%s] (+%s) %s',
				$smsReg[ 'country' ], $countries[ $smsReg[ 'country' ] ][ 'code' ], $smsReg[ 'phone' ] );
		}

		return [
			'flags'   => [
				'has_countries' => !empty( $countries ),
				'is_validated'  => $this->isProfileActive( $user )
			],
			'strings' => [
				'label_email_authentication'  => __( 'SMS Authentication', 'wp-simple-firewall' ),
				'title'                       => __( 'SMS Authentication', 'wp-simple-firewall' ),
				'provide_full_phone_number'   => __( 'Provide Your Full Mobile Telephone Number', 'wp-simple-firewall' ),
				'description_sms_auth_submit' => __( 'Click to verify your mobile number', 'wp-simple-firewall' ),
				'provided_by'                 => sprintf( __( 'Provided by %s', 'wp-simple-firewall' ),
					$this->getCon()->getHumanName() ),
				'registered_number'           => __( 'Registered Mobile Number', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'countries'        => $countries,
				'validated_number' => $validatedNumber,
			]
		];
	}

	public function isProviderEnabled() :bool {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();
		return $opts->isEnabledSmsAuth();
	}

	public function isProfileActive( \WP_User $user ) :bool {
		return $this->hasValidatedProfile( $user );
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
	 * @return $this
	 */
	private function storeCodes( \WP_User $user, array $codes ) {
		return $this->setSecret( $user, $codes );
	}

	public function getProviderName() :string {
		return 'SMS';
	}
}