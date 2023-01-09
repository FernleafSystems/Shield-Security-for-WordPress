<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

class AdeLoginGuard extends AdeBase {

	public const SLUG = 'ade_loginguard';

	public function href() :string {
		$mod = $this->getCon()->getModule_LoginGuard();
		return $mod->isModOptEnabled() ? $this->link( 'enable_antibot_check' ) : $this->link( 'enable_login_protect' );
	}

	public function title() :string {
		return __( 'AntiBot Detection Engine For Logins', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'The AntiBot Detection Engine option is enabled.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "The AntiBot Detection Engine option is disabled, removing brute force protection for login, register and lost password forms.", 'wp-simple-firewall' );
	}
}