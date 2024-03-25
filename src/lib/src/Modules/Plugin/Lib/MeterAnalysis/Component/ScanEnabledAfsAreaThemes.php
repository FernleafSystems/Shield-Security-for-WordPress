<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class ScanEnabledAfsAreaThemes extends ScanEnabledAfsAreaBase {

	public const SLUG = 'scan_enabled_afs_themes';

	protected function testIfProtected() :bool {
		return self::con()->comps->scans->AFS()->isScanEnabledThemes();
	}

	public function title() :string {
		return __( 'WordPress Theme File Scanner', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'WordPress theme files are protected against tampering.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "WordPress theme files aren't scanned for tampering.", 'wp-simple-firewall' );
	}
}