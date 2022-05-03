<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis;

class MeterLockdown extends MeterBase {

	const SLUG = 'lockdown';

	protected function getWorkingMods() :array {
		return [ $this->getCon()->getModule_Lockdown() ];
	}

	protected function title() :string {
		return __( 'Site Lockdown', 'wp-simple-firewall' );
	}

	protected function subtitle() :string {
		return __( 'How various WordPress components are locked-down', 'wp-simple-firewall' );
	}

	protected function description() :array {
		return [
			__( "This section assesses how, and whether, you've locked down certain WordPress components which, for the vast majority of website, don't need to remain enabled by default.", 'wp-simple-firewall' ),
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