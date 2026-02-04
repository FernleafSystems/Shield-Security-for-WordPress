<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class LoginCooldown extends Base {

	use Traits\OptConfigBased;

	public const SLUG = 'login_cooldown';
	public const WEIGHT = 4;

	protected function testIfProtected() :bool {
		return self::con()->opts->optGet( 'login_limit_interval' ) > 0;
	}

	protected function getOptConfigKey() :string {
		return 'login_limit_interval';
	}

	public function title() :string {
		return __( 'Login Cooldown', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'Login Cooldown system is helping prevent brute force attacks by limiting login attempts.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Brute force login attacks aren't blocked by the login cooldown system.", 'wp-simple-firewall' );
	}
}