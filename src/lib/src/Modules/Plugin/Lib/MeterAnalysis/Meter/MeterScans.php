<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Meter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;
use FernleafSystems\Wordpress\Services\Services;

class MeterScans extends MeterBase {

	public const SLUG = 'scans';

	protected function getWorkingMods() :array {
		return [ $this->getCon()->getModule_HackGuard() ];
	}

	public function title() :string {
		return __( 'Site Scanning', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Which types, and how quickly, existing vulnerabilities are discovered', 'wp-simple-firewall' );
	}

	public function description() :array {
		return [
			__( "Regular file scanning is important to ensure malicious files are caught before they can be abused.", 'wp-simple-firewall' ),
			__( "Scanning is often marketed as the most important aspect of security, but this thinking is backwards.", 'wp-simple-firewall' )
			.' '.__( "Scanning is remedial and detection of malware, for example, is a symptom of larger problem i.e. that your site is vulnerable to intrusion.", 'wp-simple-firewall' ),
			__( "It is, nevertheless, a critical component of your WordPress security because we must know when our site has been infected.", 'wp-simple-firewall' ),
			__( "In summary, scanning your site doesn't protect your site from being hacked, it tells you that you're already hacked.", 'wp-simple-firewall' ),
		];
	}

	protected function getComponents() :array {
		$FS = Services::WpFs();
		return [
			Component\ScanEnabledAfs::class,
			Component\ScanEnabledMal::class,
			Component\ScanEnabledAfsAutoRepairCore::class,
			Component\ScanEnabledAfsAutoRepairPlugins::class,
			Component\ScanEnabledAfsAutoRepairThemes::class,
			Component\ScanEnabledFileLockerWpconfig::class,
			$FS->isAccessibleFile( path_join( ABSPATH, '.htaccess' ) ) ? Component\ScanEnabledFileLockerHtaccess::class : '',
			$FS->isAccessibleFile( path_join( ABSPATH, 'index.php' ) ) ? Component\ScanEnabledFileLockerIndex::class : '',
			Services::Data()->isWindows() ? Component\ScanEnabledFileLockerWebconfig::class : '',
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