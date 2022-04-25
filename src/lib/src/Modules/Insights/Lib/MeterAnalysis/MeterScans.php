<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis;

class MeterScans extends MeterBase {

	const SLUG = 'scans';

	protected function title() :string {
		return __( 'Site Scanning', 'wp-simple-firewall' );
	}

	protected function getComponentSlugs() :array {
		return [
			'file_scanner',
			'malware_scanner',
			'auto_repair_core',
			'auto_repair_plugin',
			'auto_repair_theme',
			'filelocker_wpconfig',
			'filelocker_htaccess',
			'apc_scanner',
			'wpv_scanner',
			'vuln_autoupdate',
			'scan_freq',
		];
	}
}