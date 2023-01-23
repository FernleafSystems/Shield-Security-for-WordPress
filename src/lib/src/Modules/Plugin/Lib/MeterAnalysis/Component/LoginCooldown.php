<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Options;

class LoginCooldown extends Base {

	public const SLUG = 'login_cooldown';
	public const WEIGHT = 20;

	protected function isProtected() :bool {
		$mod = $this->getCon()->getModule_LoginGuard();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		return $mod->isModOptEnabled() && $opts->isEnabledCooldown();
	}

	public function href() :string {
		$mod = $this->getCon()->getModule_LoginGuard();
		return $mod->isModOptEnabled() ? $this->link( 'login_limit_interval' ) : $this->link( 'enable_login_protect' );
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