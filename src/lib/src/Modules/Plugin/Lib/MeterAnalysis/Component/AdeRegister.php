<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class AdeRegister extends AdeBase {

	public const SLUG = 'ade_register';

	protected function testIfProtected() :bool {
		return self::con()->comps->opts_lookup->enabledLoginProtectionArea( 'register' );
	}

	public function title() :string {
		return __( 'User Register Bot Protection', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'SPAM and bulk user registration by bots are blocked by silentCAPTCHA.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "SPAM and bulk user registration by bots isn't blocked by silentCAPTCHA.", 'wp-simple-firewall' );
	}
}