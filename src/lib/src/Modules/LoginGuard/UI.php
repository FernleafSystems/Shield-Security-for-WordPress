<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class UI extends Base\ShieldUI {

	public function getInsightsOverviewCards() :array {
		/** @var \ICWP_WPSF_FeatureHandler_LoginProtect $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		$cardSection = [
			'title'        => __( 'Login Guard', 'wp-simple-firewall' ),
			'subtitle'          => __( 'Brute Force Protection & Identity Verification', 'wp-simple-firewall' ),
			'href_options' => $mod->getUrl_AdminPage()
		];

		$cards = [];

		if ( !$mod->isModOptEnabled() ) {
			$cards[ 'mod' ] = $this->getModDisabledCard();
		}
		else {
			$bHasBotCheck = $opts->isEnabledGaspCheck() || $mod->isEnabledCaptcha();

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
					__( 'At least 1 2FA option is enabled', 'wp-simple-firewall' )
					: __( 'No 2FA options, such as Google Authenticator, are active.', 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToSection( 'section_2fa_email' ),
			];
		}

		$cardSection[ 'cards' ] = $cards;
		return [ 'login_protect' => $cardSection ];
	}

	/**
	 * @return array
	 */
	public function getInsightsConfigCardData() :array {
		/** @var \ICWP_WPSF_FeatureHandler_LoginProtect $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		$data = [
			'strings'      => [
				'title' => __( 'Login Guard', 'wp-simple-firewall' ),
				'sub'   => __( 'Brute Force Protection & Identity Verification', 'wp-simple-firewall' ),
			],
			'key_opts'     => [],
			'href_options' => $mod->getUrl_AdminPage()
		];

		if ( !$mod->isModOptEnabled() ) {
			$data[ 'key_opts' ][ 'mod' ] = $this->getModDisabledInsight();
		}
		else {
			$bHasBotCheck = $opts->isEnabledGaspCheck() || $mod->isEnabledCaptcha();

			$bBotLogin = $bHasBotCheck && $opts->isProtectLogin();
			$bBotRegister = $bHasBotCheck && $opts->isProtectRegister();
			$bBotPassword = $bHasBotCheck && $opts->isProtectLostPassword();
			$data[ 'key_opts' ][ 'bot_login' ] = [
				'name'    => __( 'Brute Force Login', 'wp-simple-firewall' ),
				'enabled' => $bBotLogin,
				'summary' => $bBotLogin ?
					__( 'Login forms are protected against bot attacks', 'wp-simple-firewall' )
					: __( 'Login forms are not protected against brute force bot attacks', 'wp-simple-firewall' ),
				'weight'  => 2,
				'href'    => $mod->getUrl_DirectLinkToOption( 'bot_protection_locations' ),
			];
			$data[ 'key_opts' ][ 'bot_register' ] = [
				'name'    => __( 'Bot User Register', 'wp-simple-firewall' ),
				'enabled' => $bBotRegister,
				'summary' => $bBotRegister ?
					__( 'Registration forms are protected against bot attacks', 'wp-simple-firewall' )
					: __( 'Registration forms are not protected against automated bots', 'wp-simple-firewall' ),
				'weight'  => 2,
				'href'    => $mod->getUrl_DirectLinkToOption( 'bot_protection_locations' ),
			];
			$data[ 'key_opts' ][ 'bot_password' ] = [
				'name'    => __( 'Brute Force Lost Password', 'wp-simple-firewall' ),
				'enabled' => $bBotPassword,
				'summary' => $bBotPassword ?
					__( 'Lost Password forms are protected against bot attacks', 'wp-simple-firewall' )
					: __( 'Lost Password forms are not protected against automated bots', 'wp-simple-firewall' ),
				'weight'  => 2,
				'href'    => $mod->getUrl_DirectLinkToOption( 'bot_protection_locations' ),
			];

			$bHas2Fa = $opts->isEmailAuthenticationActive()
					   || $opts->isEnabledGoogleAuthenticator() || $opts->isEnabledYubikey();
			$data[ 'key_opts' ][ '2fa' ] = [
				'name'    => __( 'Identity Verification', 'wp-simple-firewall' ),
				'enabled' => $bHas2Fa,
				'summary' => $bHas2Fa ?
					__( 'At least 1 2FA option is enabled', 'wp-simple-firewall' )
					: __( 'No 2FA options, such as Google Authenticator, are active', 'wp-simple-firewall' ),
				'weight'  => 2,
				'href'    => $mod->getUrl_DirectLinkToSection( 'section_2fa_email' ),
			];
		}

		return $data;
	}

	protected function getSectionWarnings( string $section ) :array {
		$aWarnings = [];

		if ( $section == 'section_brute_force_login_protection' && !$this->getCon()->isPremiumActive() ) {
			$sIntegration = $this->getPremiumOnlyIntegration();
			if ( !empty( $sIntegration ) ) {
				$aWarnings[] = sprintf( __( 'Support for login protection with %s is a Pro-only feature.', 'wp-simple-firewall' ), $sIntegration );
			}
		}

		if ( $section == 'section_2fa_email' ) {
			$aWarnings[] =
				__( '2FA by email demands that your WP site is properly configured to send email.', 'wp-simple-firewall' )
				.'<br/>'.__( 'This is a common problem and you may get locked out in the future if you ignore this.', 'wp-simple-firewall' )
				.' '.sprintf( '<a href="%s" target="_blank" class="alert-link">%s</a>', 'https://shsec.io/dd', __( 'Learn More.', 'wp-simple-firewall' ) );
		}

		return $aWarnings;
	}

	/**
	 * @return string
	 */
	private function getPremiumOnlyIntegration() {
		$aIntegrations = [
			'WooCommerce'            => 'WooCommerce',
			'Easy_Digital_Downloads' => 'Easy Digital Downloads',
			'BuddyPress'             => 'BuddyPress',
		];

		$sIntegration = '';
		foreach ( $aIntegrations as $classToIntegrate => $sName ) {
			if ( class_exists( $classToIntegrate ) ) {
				$sIntegration = $sName;
				break;
			}
		}
		return $sIntegration;
	}
}