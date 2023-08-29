<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Options;

class AdeLogin extends AdeBase {

	public const SLUG = 'ade_login';

	protected function testIfProtected() :bool {
		/** @var Options $opts */
		$opts = self::con()
					->getModule_LoginGuard()
					->getOptions();
		return parent::testIfProtected() && $opts->isProtectLogin();
	}

	public function title() :string {
		return __( 'Login Bot Protection', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'Brute force bot attacks against your WordPress login are blocked by the AntiBot Detection Engine.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Brute force login attacks by bots aren't being blocked.", 'wp-simple-firewall' );
	}
}