<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class ScanEnabledApc extends ScanEnabledBase {

	use Traits\OptConfigBased;

	public const SLUG = 'scan_enabled_apc';
	public const WEIGHT = 3;

	protected function getOptConfigKey() :string {
		return 'enabled_scan_apc';
	}

	public function title() :string {
		return __( 'Abandoned WordPress.org Plugins', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'Detection of abandoned WordPress.org plugins is enabled.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Detection of abandoned WordPress.org plugins isn't enabled.", 'wp-simple-firewall' );
	}
}