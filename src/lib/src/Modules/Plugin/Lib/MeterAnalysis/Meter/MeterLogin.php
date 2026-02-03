<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Meter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class MeterLogin extends MeterBase {

	public const SLUG = 'login';

	public function title() :string {
		return __( 'Login Protection', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'How WordPress logins are protected from brute force attacks', 'wp-simple-firewall' );
	}

	public function description() :array {
		$labels = self::con()->labels;
		return [
			\implode( ' ', [
				__( 'CAPTCHAs are horrible.', 'wp-simple-firewall' ),
				__( 'They wreck havoc on login forms by breaking Javascript and turning the user experience into a nightmare.', 'wp-simple-firewall' ),
			] ),
			\implode( ' ', [
				sprintf( __( 'To address this we created our own system called %s.', 'wp-simple-firewall' ), $labels->getBrandName( 'silentcaptcha' ) ),
				__( 'It completely replaces the need for any user CAPTCHAs and can integrate with almost any contact and user login forms.', 'wp-simple-firewall' ),
				__( "And best of all, it's completely invisible to the user!", 'wp-simple-firewall' ),
			] ),
			\implode( ' ', [
				__( 'Another crucial element of user login security is 2-Factor Authentication.', 'wp-simple-firewall' ),
				sprintf( __( '%s offers email, Yubikey, Google Authenticator and Passkeys.', 'wp-simple-firewall' ), $labels->Name ),
			] ),
		];
	}

	protected function getComponents() :array {
		return [
			Component\IpAdeThreshold::class,
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