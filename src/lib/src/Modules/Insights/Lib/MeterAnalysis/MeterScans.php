<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis;

class MeterScans extends MeterBase {

	const SLUG = 'scans';

	protected function getWorkingMods() :array {
		return [ $this->getCon()->getModule_HackGuard() ];
	}

	public function title() :string {
		return __( 'Site Scanning', 'wp-simple-firewall' );
	}

	protected function subtitle() :string {
		return __( 'Which types, and how quickly, existing vulnerabilities are discovered', 'wp-simple-firewall' );
	}

	protected function description() :array {
		return [
			__( "Regular file scanning is important to ensure malicious files are caught before they can be abused.", 'wp-simple-firewall' ),
			__( "Scanning is often marketed as the most important aspect of security, but this thinking is backwards.", 'wp-simple-firewall' )
			.' '.__( "Scanning is remedial and detection of malware, for example, is a symptom of larger problem i.e. that your site is vulnerable to intrusion.", 'wp-simple-firewall' ),
			__( "It is, nevertheless, a critical component of your WordPress security.", 'wp-simple-firewall' ),
		];
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
			'scanresults_wcf',
			'scanresults_wpv',
			'scanresults_mal',
			'scanresults_ptg',
			'scanresults_apc',
			'wpv_scanner',
			'vuln_autoupdate',
			'scan_freq',
		];
	}
}