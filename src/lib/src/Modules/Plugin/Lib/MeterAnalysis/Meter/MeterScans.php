<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Meter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class MeterScans extends MeterBase {

	public const SLUG = 'scans';

	public function title() :string {
		return __( 'Site Scanning', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Which types, and how quickly, existing vulnerabilities are discovered', 'wp-simple-firewall' );
	}

	public function description() :array {
		return [
			__( "The easiest way to know that you've been hacked is when you discover that a file has been modified or added which shouldn't have been.", 'wp-simple-firewall' ),
			\implode( ' ', [
				__( "You'll only know whether a file has been changed, or a new file added, if you're regularly scanning your WordPress filesystem.", 'wp-simple-firewall' ),
				__( "The sooner you can find any malicious files or modifications, the sooner you can prevent any abuse.", 'wp-simple-firewall' ),
				__( "The more scans you enable, the quicker you'll be alerted to potentially malicious file changes..", 'wp-simple-firewall' ),
			] ),
			\implode( ' ', [
				__( "'Malware scanning' is often marketed as the most important aspect of security, but this thinking is backwards.", 'wp-simple-firewall' ),
				__( "Discovering malware on your site isn't 'protection' - it's actually a sign that your site is already hacked.", 'wp-simple-firewall' ),
				__( "While this is good to know, it's far more useful to prevent the hack in the first place (see the IP Blocking section above).", 'wp-simple-firewall' ),
			] ),
		];
	}

	protected function getComponents() :array {
		return [
			Component\ScanEnabledAfsAreaWpCore::class,
			Component\ScanEnabledAfsAreaPlugins::class,
			Component\ScanEnabledAfsAreaThemes::class,
			Component\ScanEnabledAfsAreaWpContent::class,
			Component\ScanEnabledAfsAreaWpRoot::class,
			Component\ScanEnabledMal::class,
			Component\ScanEnabledAfsAutoRepairCore::class,
			Component\ScanEnabledAfsAutoRepairPlugins::class,
			Component\ScanEnabledAfsAutoRepairThemes::class,
			Component\ScanEnabledFileLockerWpconfig::class,
			Component\ScanEnabledFileLockerHtaccess::class,
			Component\ScanEnabledFileLockerIndex::class,
			Component\ScanEnabledFileLockerWebconfig::class,
			Component\ScanEnabledApc::class,
			Component\ScanResultsWcf::class,
			Component\ScanResultsWpv::class,
			Component\ScanResultsMal::class,
			Component\ScanResultsPtg::class,
			Component\ScanResultsApc::class,
			Component\ScanEnabledWpv::class,
			Component\ScanEnabledWpvAutoupdate::class,
			Component\ScanFrequency::class,
		];
	}
}