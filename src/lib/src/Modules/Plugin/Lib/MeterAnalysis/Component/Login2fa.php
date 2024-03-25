<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\{
	Email,
	GoogleAuth,
	Passkey,
	Yubikey
};

class Login2fa extends Base {

	use Traits\OptConfigBased;

	public const SLUG = 'login_2fa';
	public const WEIGHT = 5;

	protected function testIfProtected() :bool {
		return Email::ProviderEnabled()
			   || GoogleAuth::ProviderEnabled()
			   || Yubikey::ProviderEnabled()
			   || Passkey::ProviderEnabled();
	}

	protected function getOptConfigKey() :string {
		return 'enable_email_authentication';
	}

	public function title() :string {
		return __( '2-Factor Authentication', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'At least 1 2FA option is available to protect user accounts.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "There are no 2FA options made available to help users protect their accounts.", 'wp-simple-firewall' );
	}
}