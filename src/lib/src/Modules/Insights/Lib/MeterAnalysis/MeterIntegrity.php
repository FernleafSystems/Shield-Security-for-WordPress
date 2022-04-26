<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis;

class MeterIntegrity extends MeterBase {

	const SLUG = 'integrity';

	protected function title() :string {
		return __( 'Site Security Integrity', 'wp-simple-firewall' );
	}

	protected function subtitle() :string {
		return __( 'How WordPress security protection is looking overall', 'wp-simple-firewall' );
	}

	protected function description() :array {
		return [
			__( "There are many components to a well-protected WordPress site.", 'wp-simple-firewall' ),
			__( "This section deals with general factors that will assist you in managing your WordPress security in the most efficient way possible.", 'wp-simple-firewall' ),
		];
	}

	protected function getComponentSlugs() :array {
		$components = [
			'all',
			'ssl_certificate',
			'db_password',
			'audit_trail_enabled',
			'traffic_log_enabled',
			'report_email',
		];
		if ( !$this->getCon()->getModule_SecAdmin()->getWhiteLabelController()->isEnabled() ) {
			$components[] = 'shieldpro';
		}
		return $components;
	}
}