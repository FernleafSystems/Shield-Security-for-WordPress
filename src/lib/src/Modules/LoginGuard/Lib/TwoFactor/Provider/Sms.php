<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Sms\GetAvailableCountries;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\SureSend\SendSms;

class Sms extends BaseProvider {

	const SLUG = 'sms';

	public function getJavascriptVars() :array {
		return [
			'ajax' => [
				'profile_sms2fa_add'    => $this->getMod()->getAjaxActionData( 'profile_sms2fa_add' ),
				'profile_sms2fa_remove' => $this->getMod()->getAjaxActionData( 'profile_sms2fa_remove' ),
				'profile_sms2fa_verify' => $this->getMod()->getAjaxActionData( 'profile_sms2fa_verify' ),
			],
		];
	}

	/**
	 * @throws \Exception
	 */
	public function verifyProvisionalRegistration( string $country, string $phone, string $code ) :bool {
		$user = $this->getUser();
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

		$this->setProfileValidated( true );

		return true;
	}

	/**
	 * @throws \Exception
	 */
	public function addProvisionalRegistration( string $country, string $phone ) :string {
		$user = $this->getUser();
		$meta = $this->getCon()->getUserMeta( $user );
		$reg = is_array( $meta->sms_registration ) ? $meta->sms_registration : [];

		$country = strtoupper( $country );

		if ( !preg_match( '#^\d{7,15}$#', $phone ) ) {
			throw new \Exception( 'Phone numbers should contain only digits (0-9) and be no more than 15 digits in length.' );
		}
		if ( !preg_match( '#^[A-Z]{2}$#', $country ) ) {
			throw new \Exception( 'Invalid country selected.' ); // TODO: Verify against official countries
		}

		if ( @$reg[ 'country' ] === $country && @$reg[ 'phone' ] === $phone
			 && ( $reg[ 'verified' ] ?? false ) ) {
			throw new \Exception( 'This phone number is already verified' );
		}

		$this->setProfileValidated( false );

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
	 * @throws \Exception
	 */
	public function startLoginIntent() {
		$user = $this->getUser();
		$meta = $this->getCon()->getUserMeta( $user );

		$reg = $meta->sms_registration;
		$reg[ 'code' ] = $this->generateSimpleOTP();
		$meta->sms_registration = $reg;

		( new SendSms() )
			->setMod( $this->getMod() )
			->send2FA( $user, $meta->sms_registration[ 'code' ] );
	}

	/**
	 * @inheritDoc
	 */
	public function postSuccessActions() {
		parent::postSuccessActions();
		$meta = $this->getCon()->getUserMeta( $this->getUser() );
		$reg = $meta->sms_registration;
		unset( $reg[ 'code' ] );
		$meta->sms_registration = $reg;
		return $this;
	}

	protected function processOtp( string $otp ) :bool {
		$meta = $this->getCon()->getUserMeta( $this->getUser() );
		return !empty( $meta->sms_registration[ 'code' ] )
			   && $meta->sms_registration[ 'code' ] === strtoupper( $otp );
	}

	public function getFormField() :array {
		return [
			'slug'        => static::SLUG,
			'name'        => $this->getLoginFormParameter(),
			'type'        => 'button',
			'value'       => 'Click To Send 2FA Code via SMS',
			'placeholder' => '',
			'text'        => 'SMS Authentication',
			'classes'     => [ 'btn', 'btn-light' ],
			'help_link'   => '',
			'datas'       => [
				'ajax_intent_sms_send' => $this->getMod()->getAjaxActionData( 'intent_sms_send', true ),
				'input_otp'            => $this->getLoginFormParameter(),
			]
		];
	}

	protected function hasValidSecret() :bool {
		return true;
	}

	public function remove() {
		$this->getCon()->getUserMeta( $this->getUser() )->sms_registration = [];
		parent::remove();
	}

	protected function getProviderSpecificRenderData() :array {
		$user = $this->getUser();
		$countries = ( new GetAvailableCountries() )
			->setMod( $this->getMod() )
			->run();

		$validatedNumber = '';
		if ( $this->hasValidatedProfile() ) {
			$smsReg = $this->getCon()->getUserMeta( $user )->sms_registration;
			$validatedNumber = sprintf( '[%s] (+%s) %s',
				$smsReg[ 'country' ], $countries[ $smsReg[ 'country' ] ][ 'code' ], $smsReg[ 'phone' ] );
		}

		return [
			'flags'   => [
				'has_countries' => !empty( $countries ),
				'is_validated'  => $this->isProfileActive()
			],
			'strings' => [
				'label_email_authentication'  => __( 'SMS Authentication', 'wp-simple-firewall' ),
				'title'                       => __( 'SMS Authentication', 'wp-simple-firewall' ),
				'provide_full_phone_number'   => __( 'Provide Your Full Mobile Telephone Number', 'wp-simple-firewall' ),
				'description_sms_auth_submit' => __( 'Verifying your number will send an SMS to your phone with a verification code.', 'wp-simple-firewall' )
												 .' '.__( 'This will consume your SMS credits, if available, just as with any standard 2FA SMS.', 'wp-simple-firewall' ),
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

	public function isProfileActive() :bool {
		return $this->hasValidatedProfile();
	}

	public function getProviderName() :string {
		return 'SMS';
	}
}