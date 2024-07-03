<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class AdeLostPassword extends AdeBase {

	public const SLUG = 'ade_lostpassword';

	protected function testIfProtected() :bool {
		return self::con()->comps->opts_lookup->enabledLoginProtectionArea( 'password' );
	}

	public function title() :string {
		return __( 'Lost Password Bot Protection', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'Lost Password SPAMing by bots is blocked by silentCAPTCHA.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Lost Password SPAMing by bots isn't blocked by silentCAPTCHA.", 'wp-simple-firewall' );
	}
}