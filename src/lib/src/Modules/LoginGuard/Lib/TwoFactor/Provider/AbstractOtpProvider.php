<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Exceptions\{
	OtpNotPresentException,
	OtpVerificationFailedException
};
use FernleafSystems\Wordpress\Services\Services;

abstract class AbstractOtpProvider extends AbstractProvider {

	public function validateLoginIntent( string $hashedLoginNonce ) :bool {
		parent::validateLoginIntent( $hashedLoginNonce );

		$otp = $this->fetchOtpFromRequest();
		if ( empty( $otp ) ) {
			throw new OtpNotPresentException();
		}

		if ( !$this->processOtp( $otp ) ) {
			throw new OtpVerificationFailedException( $this->getProviderName() );
		}

		return true;
	}

	protected function fetchOtpFromRequest() :string {
		return \trim( (string)Services::Request()->request( $this->getLoginIntentFormParameter(), false, '' ) );
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