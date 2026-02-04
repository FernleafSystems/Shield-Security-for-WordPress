<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class ScanEnabledAfsAreaWpRoot extends ScanEnabledAfsAreaBase {

	public const SLUG = 'scan_enabled_afs_wproot';
	public const WEIGHT = 2;

	protected function testIfProtected() :bool {
		return self::con()->comps->scans->AFS()->isScanEnabledWpRoot();
	}

	public function title() :string {
		return __( 'WordPress Site Root File Scanner', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'Unrecognised files stored in the site root are scanned.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Unrecognised files stored in the site root aren't scanned.", 'wp-simple-firewall' );
	}
}