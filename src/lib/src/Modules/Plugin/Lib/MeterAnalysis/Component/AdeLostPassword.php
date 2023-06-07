<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Options;

class AdeLostPassword extends AdeBase {

	public const SLUG = 'ade_lostpassword';

	protected function testIfProtected() :bool {
		/** @var Options $opts */
		$opts = $this->con()
					 ->getModule_LoginGuard()
					 ->getOptions();
		return parent::testIfProtected() && $opts->isProtectLostPassword();
	}

	public function title() :string {
		return __( 'Lost Password Bot Protection', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'Lost Password SPAMing by bots are blocked by the AntiBot Detection Engine.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Lost Password SPAMing by bots aren't being blocked.", 'wp-simple-firewall' );
	}
}