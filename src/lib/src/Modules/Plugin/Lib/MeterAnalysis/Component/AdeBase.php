<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Options;

abstract class AdeBase extends Base {

	use Traits\OptConfigBased;

	protected function testIfProtected() :bool {
		$mod = $this->getCon()->getModule_LoginGuard();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		return $mod->isModOptEnabled() && $opts->isEnabledAntiBot();
	}

	protected function getOptConfigKey() :string {
		return 'bot_protection_locations';
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