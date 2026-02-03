<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class ScanEnabledAfsAreaWpCore extends ScanEnabledAfsAreaBase {

	public const SLUG = 'scan_enabled_afs_core';

	protected function testIfProtected() :bool {
		return self::con()->comps->scans->AFS()->isScanEnabledWpCore();
	}

	public function title() :string {
		return __( 'WordPress Core File Scanner', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'WordPress Core files are protected against tampering.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "WordPress files aren't scanned for tampering.", 'wp-simple-firewall' );
	}
}