<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis;

class MeterSiteIntegrity extends MeterBase {

	const SLUG = 'integrity';

	protected function title() :string {
		return __( 'Site Integrity', 'wp-simple-firewall' );
	}

	protected function getComponentSlugs() :array {
		return [
			'ssl_certificate',
			'db_password',
			'audit_trail_enabled',
			'report_email',
			'headers',
		];
	}
}