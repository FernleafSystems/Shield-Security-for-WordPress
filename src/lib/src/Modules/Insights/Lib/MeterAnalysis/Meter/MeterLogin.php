<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Meter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

class MeterLogin extends MeterBase {

	public const SLUG = 'login';

	protected function getWorkingMods() :array {
		return [
			$this->getCon()->getModule_LoginGuard(),
			$this->getCon()->getModule_UserManagement()
		];
	}

	public function title() :string {
		return __( 'Login Protection', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'How WordPress logins are protected from brute force attacks', 'wp-simple-firewall' );
	}

	public function description() :array {
		$name = $this->getCon()->getHumanName();
		return [
			sprintf( __( '%s protects the login/register/lost-password forms in a number of important ways.', 'wp-simple-firewall' ), $name ),
			__( "The most critical is the AntiBot Detection Engine which completely replaces the need for CAPTCHAs, providing a smooth user login experience.", 'wp-simple-firewall' ),
			__( "There are also 2FA options available to help enforce user identify verification.", 'wp-simple-firewall' ),
		];
	}

	protected function getComponents() :array {
		return [
			Component\AdeLoginGuard::class,
			Component\AdeLogin::class,
			Component\AdeRegister::class,
			Component\AdeLostPassword::class,
			Component\LoginCooldown::class,
			Component\TrafficRateLimiting::class,
			Component\LoginFormThirdParties::class,
			Component\Login2fa::class,
			Component\UserPasswordPolicies::class,
		];
	}
}