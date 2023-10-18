<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Options;

class Login2fa extends Base {

	use Traits\OptConfigBased;

	public const SLUG = 'login_2fa';
	public const WEIGHT = 5;

	protected function testIfProtected() :bool {
		$mod = self::con()->getModule_LoginGuard();
		/** @var Options $opts */
		$opts = $mod->opts();
		return $mod->isModOptEnabled()
			   && ( $opts->isEmailAuthenticationActive()
					|| $opts->isEnabledGoogleAuthenticator()
					|| $opts->isEnabledYubikey() );
	}

	protected function getOptConfigKey() :string {
		return 'enable_email_authentication';
	}

	public function title() :string {
		return __( '2-Factor Authentication', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'At least 1 2FA option is available to help users protect their accounts.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "There are no 2FA options made available to help users protect their accounts.", 'wp-simple-firewall' );
	}
}