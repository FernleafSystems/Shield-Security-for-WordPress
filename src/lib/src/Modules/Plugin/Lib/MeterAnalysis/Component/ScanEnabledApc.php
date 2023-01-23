<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class ScanEnabledApc extends ScanEnabledBase {

	public const SLUG = 'scan_enabled_apc';
	public const WEIGHT = 30;

	public function title() :string {
		return __( 'Abandoned WordPress.org Plugins', 'wp-simple-firewall' );
	}

	public function href() :string {
		return $this->getCon()->getModule_HackGuard()->isModOptEnabled() ?
			$this->link( 'enabled_scan_apc' ) : $this->link( 'enable_hack_protect' );
	}

	public function descProtected() :string {
		return __( 'Detection of abandoned WordPress.org plugins is enabled.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Detection of abandoned WordPress.org plugins isn't enabled.", 'wp-simple-firewall' );
	}
}