<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

class MeterScans extends MeterBase {

	const SLUG = 'scans';

	protected function title() :string {
		return __( 'Site Scanning', 'wp-simple-firewall' );
	}

	protected function buildComponents() :array {
		$mod = $this->getCon()->getModule_HackGuard();
		/** @var HackGuard\Options $opts */
		$opts = $mod->getOptions();
		$scansCon = $mod->getScansCon();

		/** @var HackGuard\Scan\Controller\Afs $afsCon */
		$afsCon = $scansCon->getScanCon( HackGuard\Scan\Controller\Afs::SCAN_SLUG );
		$fileLocker = $mod->getFileLocker();
		return [
			'file_scan'            => [
				'title'            => __( 'WordPress File Scanner', 'wp-simple-firewall' ),
				'desc_protected'   => __( 'WordPress file scanner is enabled.', 'wp-simple-firewall' ),
				'desc_unprotected' => __( "WordPress file scanner isn't enabled.", 'wp-simple-firewall' ),
				'href'             => $mod->getUrl_DirectLinkToOption( 'enable_core_file_integrity_scan' ),
				'protected'        => $afsCon->isEnabled(),
				'weight'           => 40,
			],
			'malware'              => [
				'title'            => __( 'PHP Malware Scanner', 'wp-simple-firewall' ),
				'desc_protected'   => __( 'PHP malware scanner is enabled.', 'wp-simple-firewall' ),
				'desc_unprotected' => __( "PHP malware scanner isn't enabled.", 'wp-simple-firewall' ),
				'href'             => $mod->getUrl_DirectLinkToOption( 'enable_core_file_integrity_scan' ),
				'protected'        => $afsCon->isEnabledMalwareScan(),
				'weight'           => 30,
			],
			'auto_repair_core'     => [
				'title'            => __( 'WordPress Core Auto-Repair', 'wp-simple-firewall' ),
				'desc_protected'   => __( 'Auto-repair of modified WordPress core files is enabled.', 'wp-simple-firewall' ),
				'desc_unprotected' => __( "Auto-repair of modified WordPress core files isn't enabled.", 'wp-simple-firewall' ),
				'href'             => $mod->getUrl_DirectLinkToOption( 'file_repair_areas' ),
				'protected'        => $afsCon->isEnabled() && $opts->isRepairFileWP(),
				'weight'           => 30,
			],
			'auto_repair_plugin'   => [
				'title'            => __( 'WordPress.org Plugin Auto-Repair', 'wp-simple-firewall' ),
				'desc_protected'   => __( 'Auto-repair of files from WordPress.org plugins is enabled.', 'wp-simple-firewall' ),
				'desc_unprotected' => __( "Auto-repair of files from WordPress.org plugins isn't enabled.", 'wp-simple-firewall' ),
				'href'             => $mod->getUrl_DirectLinkToOption( 'file_repair_areas' ),
				'protected'        => $afsCon->isEnabledPluginThemeScan() && $opts->isRepairFilePlugin(),
				'weight'           => 30,
			],
			'auto_repair_theme'    => [
				'title'            => __( 'WordPress.org Theme Auto-Repair', 'wp-simple-firewall' ),
				'desc_protected'   => __( 'Auto-repair of files from WordPress.org themes is enabled.', 'wp-simple-firewall' ),
				'desc_unprotected' => __( "Auto-repair of files from WordPress.org themes isn't enabled.", 'wp-simple-firewall' ),
				'href'             => $mod->getUrl_DirectLinkToOption( 'file_repair_areas' ),
				'protected'        => $afsCon->isEnabledPluginThemeScan() && $opts->isRepairFileTheme(),
				'weight'           => 20,
			],
			'file_locker_wpconfig' => [
				'title'            => sprintf( '%s - %s', 'wp-config.php', __( 'Protection', 'wp-simple-firewall' ) ),
				'desc_protected'   => sprintf( __( '%s is protected against tampering.', 'wp-simple-firewall' ), 'wp-config.php' ),
				'desc_unprotected' => sprintf( __( "%s isn't protected against tampering.", 'wp-simple-firewall' ), 'wp-config.php' ),
				'href'             => $mod->getUrl_DirectLinkToOption( 'file_locker' ),
				'protected'        => $fileLocker->isEnabled() && in_array( 'wpconfig', $opts->getFilesToLock() ),
				'weight'           => 30,
			],
			'file_locker_htaccess' => [
				'title'            => sprintf( '%s - %s', '.htaccess', __( 'Protection', 'wp-simple-firewall' ) ),
				'desc_protected'   => sprintf( __( '%s is protected against tampering.', 'wp-simple-firewall' ), '.htaccess' ),
				'desc_unprotected' => sprintf( __( "%s isn't protected against tampering.", 'wp-simple-firewall' ), '.htaccess' ),
				'href'             => $mod->getUrl_DirectLinkToOption( 'file_locker' ),
				'protected'        => $fileLocker->isEnabled() && in_array( 'root_htaccess', $opts->getFilesToLock() ),
				'weight'           => 30,
			],
			'abandoned'            => [
				'title'            => __( 'Abandoned WordPress.org Plugins', 'wp-simple-firewall' ),
				'desc_protected'   => __( 'Detection of abandoned WordPress.org plugins is enabled.', 'wp-simple-firewall' ),
				'desc_unprotected' => __( "Detection of abandoned WordPress.org plugins isn't enabled.", 'wp-simple-firewall' ),
				'href'             => $mod->getUrl_DirectLinkToOption( 'enabled_scan_apc' ),
				'protected'        => $scansCon->getScanCon( HackGuard\Scan\Controller\Apc::SCAN_SLUG )->isEnabled(),
				'weight'           => 30,
			],
			'vulnerabilities'      => [
				'title'            => __( 'Vulnerable Plugins & Themes', 'wp-simple-firewall' ),
				'desc_protected'   => __( 'Plugins and Themes are scanned for known vulnerabilities.', 'wp-simple-firewall' ),
				'desc_unprotected' => __( "Plugins and Themes aren't scanned for known vulnerabilities.", 'wp-simple-firewall' ),
				'href'             => $mod->getUrl_DirectLinkToOption( 'enable_wpvuln_scan' ),
				'protected'        => $scansCon->getScanCon( HackGuard\Scan\Controller\Wpv::SCAN_SLUG )->isEnabled(),
				'weight'           => 40,
			],
			'vuln_autoupdate'      => [
				'title'            => __( 'Auto-Update Vulnerable Plugins', 'wp-simple-firewall' ),
				'desc_protected'   => __( 'Plugins with known vulnerabilities are automatically updated to protect your site.', 'wp-simple-firewall' ),
				'desc_unprotected' => __( "Plugins with known vulnerabilities aren't automatically updated to protect your site.", 'wp-simple-firewall' ),
				'href'             => $mod->getUrl_DirectLinkToOption( 'wpvuln_scan_autoupdate' ),
				'protected'        => $opts->isWpvulnAutoupdatesEnabled(),
				'weight'           => 10,
			],
			'scan_freq'            => [
				'title'            => __( 'Scanning Frequency', 'wp-simple-firewall' ),
				'desc_protected'   => __( 'Scans are run against your site at least twice per day.', 'wp-simple-firewall' ),
				'desc_unprotected' => __( "Scans are run against your site once per day at most.", 'wp-simple-firewall' ),
				'href'             => $mod->getUrl_DirectLinkToOption( 'scan_frequency' ),
				'protected'        => $opts->getScanFrequency() > 1,
				'weight'           => 10,
			],
		];
	}
}