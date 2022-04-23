<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\{
	LoginGuard,
	UserManagement
};

class MeterUsers extends MeterBase {

	const SLUG = 'users';

	protected function title() :string {
		return __( 'User Protection', 'wp-simple-firewall' );
	}

	protected function buildComponents() :array {
		$mod = $this->getCon()->getModule_LoginGuard();
		/** @var LoginGuard\Options $optsLG */
		$optsLG = $mod->getOptions();

		$modUM = $this->getCon()->getModule_UserManagement();
		/** @var UserManagement\Options $optsUM */
		$optsUM = $modUM->getOptions();
		$passPoliciesOn = $optsUM->isPasswordPoliciesEnabled();

		return [
			'cooldown'      => [
				'title'            => __( 'Login Cooldown', 'wp-simple-firewall' ),
				'desc_protected'   => __( 'Login Cooldown system is helping prevent brute force attacks by limiting login attempts.', 'wp-simple-firewall' ),
				'desc_unprotected' => __( "Brute force login attacks are not blocked by the login cooldown system.", 'wp-simple-firewall' ),
				'href'             => $mod->getUrl_DirectLinkToOption( 'login_limit_interval' ),
				'protected'        => $optsLG->isEnabledCooldown(),
				'weight'           => 20,
			],
			'ade_login'     => [
				'title'            => __( 'Login Bot Protection', 'wp-simple-firewall' ),
				'desc_protected'   => __( 'Brute force bot attacks against your WordPress login are blocked by the AntiBot Detection Engine.', 'wp-simple-firewall' ),
				'desc_unprotected' => __( "Brute force login attacks by bots aren't being blocked.", 'wp-simple-firewall' ),
				'href'             => $mod->getUrl_DirectLinkToOption( 'enable_antibot_check' ),
				'protected'        => $optsLG->isEnabledAntiBot() && $optsLG->isProtectLogin(),
				'weight'           => 30,
			],
			'ade_register'  => [
				'title'            => __( 'Register Bot Protection', 'wp-simple-firewall' ),
				'desc_protected'   => __( 'SPAM and bulk user registration by bots are blocked by the AntiBot Detection Engine.', 'wp-simple-firewall' ),
				'desc_unprotected' => __( "SPAM and bulk user registration by bots aren't being blocked.", 'wp-simple-firewall' ),
				'href'             => $mod->getUrl_DirectLinkToOption( 'enable_antibot_check' ),
				'protected'        => $optsLG->isEnabledAntiBot() && $optsLG->isProtectRegister(),
				'weight'           => 30,
			],
			'ade_password'  => [
				'title'            => __( 'Lost Password Bot Protection', 'wp-simple-firewall' ),
				'desc_protected'   => __( 'Lost Password SPAMing by bots are blocked by the AntiBot Detection Engine.', 'wp-simple-firewall' ),
				'desc_unprotected' => __( "Lost Password SPAMing by bots aren't being blocked.", 'wp-simple-firewall' ),
				'href'             => $mod->getUrl_DirectLinkToOption( 'bot_protection_locations' ),
				'protected'        => $optsLG->isEnabledAntiBot() && $optsLG->isProtectLostPassword(),
				'weight'           => 30,
			],
			'2fa'           => [
				'title'            => __( '2-Factor Authentication', 'wp-simple-firewall' ),
				'desc_protected'   => __( 'At least 1 2FA option is available to help users protect their accounts.', 'wp-simple-firewall' ),
				'desc_unprotected' => __( "There are no 2FA options made available to help users protect their accounts.", 'wp-simple-firewall' ),
				'href'             => $mod->getUrl_DirectLinkToOption( 'enable_email_authentication' ),
				'protected'        => $optsLG->isEmailAuthenticationActive()
									  || $optsLG->isEnabledGoogleAuthenticator()
									  || $optsLG->isEnabledYubikey()
									  || $optsLG->isEnabledU2F(),
				'weight'           => 30,
			],
			'pass_policies' => [
				'title'            => __( 'Password Policies', 'wp-simple-firewall' ),
				'desc_protected'   => __( 'Password policies are enabled to help promote good password hygiene.', 'wp-simple-firewall' ),
				'desc_unprotected' => __( "Password polices aren't enabled which may lead to poor password hygiene.", 'wp-simple-firewall' ),
				'href'             => $mod->getUrl_DirectLinkToOption( 'enable_password_policies' ),
				'protected'        => $passPoliciesOn,
				'weight'           => 30,
			],
			'pass_pwned'    => [
				'title'            => __( 'Pwned Passwords', 'wp-simple-firewall' ),
				'desc_protected'   => __( 'Pwned passwords are blocked from being set by any user.', 'wp-simple-firewall' ),
				'desc_unprotected' => __( "Pwned passwords are allowed to be used.", 'wp-simple-firewall' ),
				'href'             => $mod->getUrl_DirectLinkToOption( 'pass_prevent_pwned' ),
				'protected'        => $passPoliciesOn && $optsUM->isPassPreventPwned(),
				'weight'           => 30,
			],
			'pass_str'      => [
				'title'            => __( 'Strong Passwords', 'wp-simple-firewall' ),
				'desc_protected'   => __( 'All new passwords are required to be be of high strength.', 'wp-simple-firewall' ),
				'desc_unprotected' => __( "There is no requirement for strong user passwords", 'wp-simple-firewall' ),
				'href'             => $mod->getUrl_DirectLinkToOption( 'pass_min_strength' ),
				'protected'        => $passPoliciesOn && $optsUM->getPassMinStrength() >= 3,
				'weight'           => 20,
			],
		];
	}
}