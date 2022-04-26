<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis;

class MeterLockdown extends MeterBase {

	const SLUG = 'lockdown';

	protected function title() :string {
		return __( 'Site Lockdown', 'wp-simple-firewall' );
	}

	protected function subtitle() :string {
		return __( 'How various WordPress components are locked-down', 'wp-simple-firewall' );
	}

	protected function description() :array {
		return [
			__( "The section forms the core foundation of long-term, powerful WordPress protection.", 'wp-simple-firewall' ),
			__( "Your biggest threat comes from automated bots, so detecting them quickly and blocking them early is your greatest source of protection.", 'wp-simple-firewall' ),
		];
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