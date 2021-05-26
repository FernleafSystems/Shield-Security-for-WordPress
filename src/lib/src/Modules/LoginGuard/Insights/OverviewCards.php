<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Insights;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;

class OverviewCards extends Shield\Modules\Base\Insights\OverviewCards {

	protected function buildModCards() :array {
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();

		$cards = [];

		if ( $mod->isModOptEnabled() ) {

			$hasBotCheck = $opts->isEnabledAntiBot() || $opts->isEnabledGaspCheck() || $mod->isEnabledCaptcha();

			$boLogin = $hasBotCheck && $opts->isProtectLogin();
			$botReg = $hasBotCheck && $opts->isProtectRegister();
			$botPassword = $hasBotCheck && $opts->isProtectLostPassword();
			$cards[ 'bot_login' ] = [
				'name'    => __( 'Brute Force Login', 'wp-simple-firewall' ),
				'state'   => $boLogin ? 1 : -1,
				'summary' => $boLogin ?
					__( 'Login forms are protected against bot attacks', 'wp-simple-firewall' )
					: __( "Login forms aren't protected against brute force bot attacks", 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToOption( 'bot_protection_locations' ),
			];
			$cards[ 'bot_register' ] = [
				'name'    => __( 'Bot User Register', 'wp-simple-firewall' ),
				'state'   => $botReg ? 1 : -1,
				'summary' => $botReg ?
					__( 'Registration forms are protected against bot attacks', 'wp-simple-firewall' )
					: __( "Registration forms aren't protected against automated bots", 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToOption( 'bot_protection_locations' ),
			];
			$cards[ 'bot_password' ] = [
				'name'    => __( 'Brute Force Lost Password', 'wp-simple-firewall' ),
				'state'   => $botPassword ? 1 : -1,
				'summary' => $botPassword ?
					__( 'Lost Password forms are protected against bot attacks', 'wp-simple-firewall' )
					: __( "Lost Password forms aren't protected against automated bots", 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToOption( 'bot_protection_locations' ),
			];

			$bHas2Fa = $opts->isEmailAuthenticationActive()
					   || $opts->isEnabledGoogleAuthenticator() || $opts->isEnabledYubikey();
			$cards[ '2fa' ] = [
				'name'    => __( 'Identity Verification', 'wp-simple-firewall' ),
				'state'   => $bHas2Fa ? 1 : -1,
				'summary' => $bHas2Fa ?
					__( 'At least one 2FA option is available', 'wp-simple-firewall' )
					: __( 'No 2FA options, such as Google Authenticator, are active.', 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToSection( 'section_2fa_email' ),
			];
		}

		return $cards;
	}

	protected function getSectionTitle() :string {
		return __( 'Login Guard', 'wp-simple-firewall' );
	}

	protected function getSectionSubTitle() :string {
		return __( 'Brute Force Protection & Identity Verification', 'wp-simple-firewall' );
	}
}