<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class UserPasswordStrength extends UserPasswordPoliciesBase {

	public const MINIMUM_EDITION = 'starter';
	public const SLUG = 'user_pass_strength';

	protected function getOptConfigKey() :string {
		return 'pass_min_strength';
	}

	protected function testIfProtected() :bool {
		return self::con()->opts->optGet( 'pass_min_strength' ) >= 3;
	}

	public function title() :string {
		return __( 'Strong Passwords', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'All new passwords are required to be be of high strength.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "There is no requirement for strong user passwords.", 'wp-simple-firewall' );
	}
}