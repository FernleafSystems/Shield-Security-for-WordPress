<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class AdeTreshold extends AdeBase {

	public const SLUG = 'ade_threshold';

	public function href() :string {
		$mod = $this->getCon()->getModule_LoginGuard();
		return $mod->isModOptEnabled() ? $this->link( 'antibot_minimum' ) : $this->link( 'enable_login_protect' );
	}

	public function title() :string {
		return __( 'AntiBot Detection Engine', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'AntiBot Detection Engine is enabled with a minimum bot-score threshold.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "AntiBot Detection Engine is disabled as there is no minimum bot-score threshold provided.", 'wp-simple-firewall' );
	}
}