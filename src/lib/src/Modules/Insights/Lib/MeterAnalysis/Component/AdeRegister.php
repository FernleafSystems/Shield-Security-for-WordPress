<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Options;

class AdeRegister extends AdeBase {

	public const SLUG = 'ade_register';

	protected function isProtected() :bool {
		/** @var Options $opts */
		$opts = $this->getCon()
					 ->getModule_LoginGuard()
					 ->getOptions();
		return parent::isProtected() && $opts->isProtectRegister();
	}

	public function title() :string {
		return __( 'User Register Bot Protection', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'SPAM and bulk user registration by bots are blocked by the AntiBot Detection Engine.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "SPAM and bulk user registration by bots aren't being blocked.", 'wp-simple-firewall' );
	}
}