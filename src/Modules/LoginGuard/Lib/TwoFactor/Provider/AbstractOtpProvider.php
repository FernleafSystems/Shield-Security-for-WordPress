<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Exceptions\{
	OtpNotPresentException,
	OtpVerificationFailedException
};

abstract class AbstractOtpProvider extends AbstractProvider {

	public function validateLoginIntent( string $hashedLoginNonce, array $submitted ) :bool {
		parent::validateLoginIntent( $hashedLoginNonce, $submitted );

		$otp = $this->otpFrom( $submitted );
		if ( empty( $otp ) ) {
			throw new OtpNotPresentException();
		}

		if ( !$this->processOtp( $otp ) ) {
			throw new OtpVerificationFailedException( $this->getProviderName() );
		}

		return true;
	}

	/**
	 * This provider's OTP from the submitted login form. The form is request input and a field can arrive in any
	 * shape, so anything but a string reads as no OTP.
	 */
	private function otpFrom( array $submitted ) :string {
		$otp = $submitted[ $this->getLoginIntentFormParameter() ] ?? '';
		return \is_string( $otp ) ? \trim( $otp ) : '';
	}

	public function getFormField() :array {
		return [
			'slug'        => static::ProviderSlug(),
			'name'        => $this->getLoginIntentFormParameter(),
			'type'        => 'text',
			'placeholder' => '',
			'value'       => '',
			'text'        => sprintf( '%s: %s', $this->getProviderName(), 'OTP' ),
			'description' => __( 'Provide the One-Time Password from your 2FA login device', 'wp-simple-firewall' ),
			'help_link'   => ''
		];
	}

	abstract protected function processOtp( string $otp ) :bool;
}