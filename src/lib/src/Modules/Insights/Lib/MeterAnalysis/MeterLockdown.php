<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis;

class MeterLockdown extends MeterBase {

	const SLUG = 'lockdown';

	protected function title() :string {
		return __( 'Site Lockdown', 'wp-simple-firewall' );
	}

	protected function getComponentSlugs() :array {
		return [
			'secadmin',
			'secadmin_admins',
			'secadmin_options',
			'lockdown_xmlrpc',
			'lockdown_file_editing',
			'author_discovery',
			'anonymous_rest',
		];
	}
}