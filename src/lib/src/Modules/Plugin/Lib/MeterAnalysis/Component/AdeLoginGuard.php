<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class AdeLoginGuard extends AdeBase {

	public const SLUG = 'ade_loginguard';

	protected function getOptConfigKey() :string {
		return 'enable_antibot_check';
	}

	public function title() :string {
		return __( 'silentCAPTCHA For Logins', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'silentCAPTCHA option is enabled.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "silentCAPTCHA option is disabled, leaving login, register and lost password forms unprotected.", 'wp-simple-firewall' );
	}
}