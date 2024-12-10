<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	MfaSmsAdd,
	MfaSmsIntentSend,
	MfaSmsRemove,
	MfaSmsVerify
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Sms\GetAvailableCountries;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\SureSend\SendSms;
use FernleafSystems\Wordpress\Services\Services;

class Sms extends AbstractShieldProvider {

	protected const SLUG = 'sms';

	public function getJavascriptVars() :array {
		return [
			'ajax'  => [
				'profile_sms2fa_add'    => ActionData::Build( MfaSmsAdd::class ),
				'profile_sms2fa_remove' => ActionData::Build( MfaSmsRemove::class ),
				'profile_sms2fa_verify' => ActionData::Build( MfaSmsVerify::class ),
			],
			'flags' => [
				'is_available' => $this->isProviderAvailableToUser(),
			],
		];
	}

	/**
	 * @throws \Exception
	 */
	public function verifyProvisionalRegistration( string $country, string $phone, string $code ) :bool {
		$meta = self::con()->user_metas->for( $this->getUser() );
		$reg = \is_array( $meta->sms_registration ) ? $meta->sms_registration : [];

		if ( @$reg[ 'country' ] === $country && @$reg[ 'phone' ] === $phone
			 && ( $reg[ 'verified' ] ?? false ) ) {
			throw new \Exception( 'This Phone number is already added and verified' );
		}
		if ( empty( $reg[ 'code' ] ) ) {
			throw new \Exception( "The verification code couldn't be verified because the profile wasn't ready." );
		}
		if ( $reg[ 'code' ] !== \trim( \strtoupper( $code ) ) ) {
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
		$meta = self::con()->user_metas->for( $user );
		$reg = \is_array( $meta->sms_registration ) ? $meta->sms_registration : [];

		$country = \strtoupper( $country );

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
			'code'     => LoginGuard\Lib\TwoFactor\Utilties\OneTimePassword::Generate(),
			'verified' => false,
		];

		( new SendSms() )->send2FA( $user, $meta->sms_registration[ 'code' ] );

		return $meta->sms_registration[ 'code' ];
	}

	/**
	 * @throws \Exception
	 */
	public function startLoginIntent() {
		$meta = self::con()->user_metas->for( $this->getUser() );

		$reg = $meta->sms_registration;
		$reg[ 'code' ] = LoginGuard\Lib\TwoFactor\Utilties\OneTimePassword::Generate();
		$meta->sms_registration = $reg;

		( new SendSms() )->send2FA( $this->getUser(), $meta->sms_registration[ 'code' ] );
	}

	public function postSuccessActions() :void {
		parent::postSuccessActions();
		$meta = self::con()->user_metas->for( $this->getUser() );
		$reg = $meta->sms_registration;
		unset( $reg[ 'code' ] );
		$meta->sms_registration = $reg;
	}

	protected function processOtp( string $otp ) :bool {
		$meta = self::con()->user_metas->for( $this->getUser() );
		return !empty( $meta->sms_registration[ 'code' ] )
			   && $meta->sms_registration[ 'code' ] === \strtoupper( $otp );
	}

	public function getFormField() :array {
		return [
			'slug'        => static::ProviderSlug(),
			'name'        => $this->getLoginIntentFormParameter(),
			'type'        => 'button',
			'value'       => 'Click To Send 2FA Code via SMS',
			'placeholder' => '',
			'text'        => 'SMS Authentication',
			'classes'     => [ 'btn', 'btn-light' ],
			'help_link'   => '',
			'datas'       => [
				'ajax_intent_sms_send' => ActionData::BuildJson( MfaSmsIntentSend::class ),
				'input_otp'            => $this->getLoginIntentFormParameter(),
			]
		];
	}

	protected function hasValidSecret() :bool {
		return true;
	}

	public function removeFromProfile() :void {
		self::con()->user_metas->for( $this->getUser() )->sms_registration = [];
		parent::removeFromProfile();
	}

	protected function getUserProfileFormRenderData() :array {
		$countries = ( new GetAvailableCountries() )->run();

		$validatedNumber = '';
		if ( $this->hasValidatedProfile() ) {
			$smsReg = self::con()->user_metas->for( $this->getUser() )->sms_registration;
			$validatedNumber = sprintf( '[%s] (+%s) %s',
				$smsReg[ 'country' ], $countries[ $smsReg[ 'country' ] ][ 'code' ], $smsReg[ 'phone' ] );
		}

		return Services::DataManipulation()->mergeArraysRecursive(
			parent::getUserProfileFormRenderData(),
			[
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
					'provided_by'                 => sprintf( __( 'Provided by %s', 'wp-simple-firewall' ), self::con()->labels->Name ),
					'registered_number'           => __( 'Registered Mobile Number', 'wp-simple-firewall' ),
				],
				'vars'    => [
					'countries'        => $countries,
					'validated_number' => $validatedNumber,
				]
			]
		);
	}

	public function isProviderEnabled() :bool {
		return static::ProviderEnabled();
	}

	public function isProfileActive() :bool {
		return $this->hasValidatedProfile();
	}

	public static function ProviderName() :string {
		return __( 'SMS' );
	}
}