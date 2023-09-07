<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Options;

class ScanEnabledAfsAutoRepairCore extends ScanEnabledAfsAutoRepairBase {

	public const SLUG = 'scan_enabled_afs_autorepair_core';
	public const WEIGHT = 6;

	protected function getOptConfigKey() :string {
		return 'file_repair_areas';
	}

	protected function testIfProtected() :bool {
		/** @var Options $opts */
		$opts = self::con()->getModule_HackGuard()->opts();
		return parent::testIfProtected() && $opts->isRepairFileWP();
	}

	public function title() :string {
		return __( 'WordPress Core Auto-Repair', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'Auto-repair of modified WordPress core files is enabled.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Auto-repair of modified WordPress core files isn't enabled.", 'wp-simple-firewall' );
	}
}