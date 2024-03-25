<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class ScanEnabledAfsAutoRepairPlugins extends ScanEnabledAfsAutoRepairBase {

	public const SLUG = 'scan_enabled_afs_autorepair_plugins';
	public const WEIGHT = 4;

	protected function getOptConfigKey() :string {
		return 'file_repair_areas';
	}

	protected function testIfProtected() :bool {
		return self::con()->comps->scans->AFS()->isRepairFilePlugin();
	}

	public function title() :string {
		return __( 'WordPress.org Plugin Auto-Repair', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'Auto-repair of files from WordPress.org plugins is enabled.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Auto-repair of files from WordPress.org plugins isn't enabled.", 'wp-simple-firewall' );
	}
}