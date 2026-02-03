<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class ScanEnabledAfsAreaPlugins extends ScanEnabledAfsAreaBase {

	public const SLUG = 'scan_enabled_afs_plugins';

	protected function testIfProtected() :bool {
		return self::con()->comps->scans->AFS()->isScanEnabledPlugins();
	}

	public function title() :string {
		return __( 'WordPress Plugin File Scanner', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'WordPress plugin files are protected against tampering.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "WordPress plugin files aren't scanned for tampering.", 'wp-simple-firewall' );
	}
}