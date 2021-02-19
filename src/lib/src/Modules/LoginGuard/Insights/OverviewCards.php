<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Insights;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;

class OverviewCards extends Shield\Modules\Base\Insights\OverviewCards {

	public function build() :array {
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();

		$cardSection = [
			'title'        => __( 'Login Guard', 'wp-simple-firewall' ),
			'subtitle'     => __( 'Brute Force Protection & Identity Verification', 'wp-simple-firewall' ),
			'href_options' => $mod->getUrl_AdminPage()
		];

		$cards = [];

		if ( !$mod->isModOptEnabled() ) {
			$cards[ 'mod' ] = $this->getModDisabledCard();
		}
		else {
			$bHasBotCheck = $opts->isEnabledGaspCheck() || $mod->isEnabledCaptcha()
							|| $opts->isEnabledAntiBot();

			$bBotLogin = $bHasBotCheck && $opts->isProtectLogin();
			$bBotRegister = $bHasBotCheck && $opts->isProtectRegister();
			$bBotPassword = $bHasBotCheck && $opts->isProtectLostPassword();
			$cards[ 'bot_login' ] = [
				'name'    => __( 'Brute Force Login', 'wp-simple-firewall' ),
				'state'   => $bBotLogin ? 1 : -1,
				'summary' => $bBotLogin ?
					__( 'Login forms are protected against bot attacks', 'wp-simple-firewall' )
					: __( "Login forms aren't protected against brute force bot attacks", 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToOption( 'bot_protection_locations' ),
			];
			$cards[ 'bot_register' ] = [
				'name'    => __( 'Bot User Register', 'wp-simple-firewall' ),
				'state'   => $bBotRegister ? 1 : -1,
				'summary' => $bBotRegister ?
					__( 'Registration forms are protected against bot attacks', 'wp-simple-firewall' )
					: __( "Registration forms aren't protected against automated bots", 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToOption( 'bot_protection_locations' ),
			];
			$cards[ 'bot_password' ] = [
				'name'    => __( 'Brute Force Lost Password', 'wp-simple-firewall' ),
				'state'   => $bBotPassword ? 1 : -1,
				'summary' => $bBotPassword ?
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

		$cardSection[ 'cards' ] = $cards;
		return [ 'login_protect' => $cardSection ];
	}
}