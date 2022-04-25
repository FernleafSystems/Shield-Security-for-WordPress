<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis;

class MeterUsers extends MeterBase {

	const SLUG = 'users';

	protected function title() :string {
		return __( 'Login and Brute Force Protection', 'wp-simple-firewall' );
	}

	protected function getComponentSlugs() :array {
		return [
			'admin_user',
			'cooldown',
			'ade_login',
			'ade_register',
			'ade_lostpassword',
			'2fa',
			'pass_policies',
			'pass_pwned',
			'pass_str',
		];
	}
}