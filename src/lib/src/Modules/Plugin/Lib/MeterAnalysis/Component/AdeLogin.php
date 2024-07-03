<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class AdeLogin extends AdeBase {

	public const SLUG = 'ade_login';

	protected function testIfProtected() :bool {
		return self::con()->comps->opts_lookup->enabledLoginProtectionArea( 'login' );
	}

	public function title() :string {
		return __( 'Login Bot Protection', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'Brute force bot attacks against your WordPress login are detected & blocked by silentCAPTCHA.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Brute force login attacks by bots aren't being blocked.", 'wp-simple-firewall' );
	}
}