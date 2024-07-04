<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

abstract class AdeBase extends Base {

	use Traits\OptConfigBased;

	protected function testIfProtected() :bool {
		return self::con()->comps->opts_lookup->enabledLoginGuardAntiBotCheck();
	}

	protected function getOptConfigKey() :string {
		return self::con()->comps->opts_lookup->enabledLoginGuardAntiBotCheck() ? 'bot_protection_locations' : 'enable_antibot_check';
	}

	public function title() :string {
		return __( 'silentCAPTCHA For Logins', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'The silentCAPTCHA option is enabled.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "The silentCAPTCHA option is disabled, removing brute force protection for login, register and lost password forms.", 'wp-simple-firewall' );
	}
}