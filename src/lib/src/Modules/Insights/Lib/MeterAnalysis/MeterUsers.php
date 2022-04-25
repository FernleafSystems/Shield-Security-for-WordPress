<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis;

class MeterUsers extends MeterBase {

	const SLUG = 'users';

	protected function title() :string {
		return __( 'User Protection', 'wp-simple-firewall' );
	}

	protected function getComponentSlugs() :array {
		return [
			'admin_user',
			'secadmin_admins',
			'2fa',
			'sessions_idle',
			'users_inactive',
			'pass_policies',
			'pass_pwned',
			'pass_str',
		];
	}
}