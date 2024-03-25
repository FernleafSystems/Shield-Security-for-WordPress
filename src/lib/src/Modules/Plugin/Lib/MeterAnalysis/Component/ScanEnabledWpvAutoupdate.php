<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class ScanEnabledWpvAutoupdate extends Base {

	use Traits\OptConfigBased;

	public const MINIMUM_EDITION = 'starter';
	public const SLUG = 'scan_enabled_wpv_autoupdate';

	protected function getOptConfigKey() :string {
		return 'wpvuln_scan_autoupdate';
	}

	protected function testIfProtected() :bool {
		$wpv = self::con()->comps->scans->WPV();
		return $wpv->isEnabled() && $wpv->isAutoupdatesEnabled();
	}

	public function title() :string {
		return __( 'Auto-Update Vulnerable Plugins', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'Plugins with known vulnerabilities are automatically updated to protect your site.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Plugins with known vulnerabilities aren't automatically updated to protect your site.", 'wp-simple-firewall' );
	}
}