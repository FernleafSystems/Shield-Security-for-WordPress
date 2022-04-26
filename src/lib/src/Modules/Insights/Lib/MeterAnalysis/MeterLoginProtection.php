<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis;

class MeterLoginProtection extends MeterBase {

	const SLUG = 'login';

	protected function title() :string {
		return __( 'Login Protection', 'wp-simple-firewall' );
	}

	protected function subtitle() :string {
		return __( 'How WordPress logins are protected from brute force attacks', 'wp-simple-firewall' );
	}

	protected function description() :array {
		$name = $this->getCon()->getHumanName();
		return [
			sprintf( __( '%s protects the login/register/lost-password forms in a number of important ways.', 'wp-simple-firewall' ), $name ),
			__( "The most critical is the AntiBot Detection Engine which completely replaces the need for CAPTCHAs, providing a smooth user login experience.", 'wp-simple-firewall' ),
			__( "There are also 2FA options available to help enforce user identify verification.", 'wp-simple-firewall' ),
		];
	}

	protected function getComponentSlugs() :array {
		return [
			'ade_loginguard',
			'ade_login',
			'ade_register',
			'ade_lostpassword',
			'cooldown',
			'traffic_rate_limiting',
			'tp_login_forms',
			'2fa',
			'pass_policies',
		];
	}
}