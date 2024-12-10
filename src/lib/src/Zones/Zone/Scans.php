<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

class Scans extends Base {

	public function tooltip() :string {
		return 'Edit settings for the entire Scans Zone';
	}

	public function actions() :array {
		$con = self::con();
		return \array_merge( parent::actions(), [
			'scan_results' => [
				'title'   => __( 'View Scan Results', 'wp-simple-firewall' ),
				'href'    => $con->plugin_urls->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RESULTS ),
				'icon'    => $con->svgs->raw( 'shield-shaded' ),
				'classes' => [],
			]
		] );
	}

	public function components() :array {
		return [
			Component\FileScanning::class,
			Component\VulnerabilityScanning::class,
			Component\FileLocker::class,
			Component\WordpressUpdates::class,
			Component\ServerSoftwareStatus::class,
		];
	}

	public function description() :array {
		return [
			\implode( ' ', [
				__( 'In spite of our best efforts to secure our websites, breaches can still happen.', 'wp-simple-firewall' ),
				__( 'Scanning your WordPress files for changes is the most reliable way to discover whether your website has been compromised.', 'wp-simple-firewall' ),
			] ),
			\implode( ' ', [
				__( 'Speaking of breaches, vulnerabilities within plugins and themes that allow breaches to happen, are a prime target for attacks so we must identify them early.', 'wp-simple-firewall' ),
				sprintf( __( '%s can also scan your plugins & themes for known vulnerabilities.', 'wp-simple-firewall' ), 'ShieldPRO' ),
				__( 'We also consider plugins that have been abandoned by their authors to represent a vulnerability on your site.', 'wp-simple-firewall' ),
			] ),
			\implode( ' ', [
				sprintf( __( "%s is %s's exclusive service that protects your wp-config.php file.", 'wp-simple-firewall' ), 'FileLocker', 'ShieldPRO' ),
				__( 'With it you can be instantly alerted to changes to the file with a clear view on the precise changes and easy next-steps on how to proceed.', 'wp-simple-firewall' ),
			] ),
		];
	}

	public function icon() :string {
		return 'shield-shaded';
	}

	public function title() :string {
		return __( 'Scans', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Regular scanning help identify compromise and prevent exploitation of vulnerabilies.', 'wp-simple-firewall' );
	}

	protected function getUnderlyingModuleZone() :?string {
		return Component\Modules\ModuleScans::class;
	}
}