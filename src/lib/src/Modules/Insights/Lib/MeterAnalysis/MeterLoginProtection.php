<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis;

class MeterLoginProtection extends MeterBase {

	const SLUG = 'login';

	protected function title() :string {
		return __( 'Login Protection', 'wp-simple-firewall' );
	}

	protected function getComponentSlugs() :array {
		return [
			'cooldown',
			'ade_loginguard',
			'ade_login',
			'ade_register',
			'ade_lostpassword',
			'tp_login_forms',
			'2fa',
			'pass_policies',
		];
	}
}