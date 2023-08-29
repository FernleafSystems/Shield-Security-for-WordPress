<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Meter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class MeterLogin extends MeterBase {

	public const SLUG = 'login';

	protected function getWorkingMods() :array {
		return [
			self::con()->getModule_LoginGuard(),
			self::con()->getModule_UserManagement()
		];
	}

	public function title() :string {
		return __( 'Login Protection', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'How WordPress logins are protected from brute force attacks', 'wp-simple-firewall' );
	}

	public function description() :array {
		$name = self::con()->getHumanName();
		return [
			\implode( ' ', [
				__( "CAPTCHAs are horrible.", 'wp-simple-firewall' ),
				__( "They wreck havoc on login forms by breaking Javascript and turning the user experience into a nightmare.", 'wp-simple-firewall' ),
			] ),
			\implode( ' ', [
				__( "To address this we created our own system called the AntiBot Detection Engine.", 'wp-simple-firewall' ),
				__( "It completely replaces the need for any CAPTCHAS and can integrate with almost any other contact forms and user login forms.", 'wp-simple-firewall' ),
				__( "And best of all, it's completely invisible to the user!", 'wp-simple-firewall' ),
			] ),
			\implode( ' ', [
				__( "Another crucial element of user login security is 2-Factor Authentication.", 'wp-simple-firewall' ),
				sprintf( __( "%s offers email, Yubikey, Google Authenticator and U2F.", 'wp-simple-firewall' ), $name ),
				__( "Unfortunately U2F has been disabled by-default on most modern browsers, but it's still provided here for backwards compatibility.", 'wp-simple-firewall' ),
			] ),
		];
	}

	protected function getComponents() :array {
		return [
			Component\IpAdeThreshold::class,
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