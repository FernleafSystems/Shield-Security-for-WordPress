<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class ScanEnabledAfsAutoRepairThemes extends ScanEnabledAfsAutoRepairBase {

	public const SLUG = 'scan_enabled_afs_autorepair_themes';
	public const WEIGHT = 2;

	protected function getOptConfigKey() :string {
		return 'file_repair_areas';
	}

	protected function testIfProtected() :bool {
		return self::con()->comps->scans->AFS()->isRepairFileTheme();
	}

	public function title() :string {
		return __( 'WordPress.org Theme Auto-Repair', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'Auto-repair of files from WordPress.org themes is enabled.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Auto-repair of files from WordPress.org themes isn't enabled.", 'wp-simple-firewall' );
	}
}