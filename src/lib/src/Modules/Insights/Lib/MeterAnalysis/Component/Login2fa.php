<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Options;

class Login2fa extends Base {

	public const SLUG = 'login_2fa';
	public const WEIGHT = 50;

	protected function isProtected() :bool {
		$mod = $this->getCon()->getModule_LoginGuard();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		return $mod->isModOptEnabled()
			   && ( $opts->isEmailAuthenticationActive()
					|| $opts->isEnabledGoogleAuthenticator()
					|| $opts->isEnabledYubikey()
					|| $opts->isEnabledU2F() );
	}

	public function href() :string {
		$mod = $this->getCon()->getModule_LoginGuard();
		return $mod->isModOptEnabled() ? $this->link( 'enable_email_authentication' ) : $this->link( 'enable_login_protect' );
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