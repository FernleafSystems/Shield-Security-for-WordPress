<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Options;

class ScanEnabledAfsAutoRepairCore extends Base {

	use Traits\OptConfigBased;

	public const SLUG = 'scan_enabled_afs_autorepair_core';
	public const WEIGHT = 6;

	protected function getOptConfigKey() :string {
		return 'file_repair_areas';
	}

	protected function testIfProtected() :bool {
		$mod = $this->getCon()->getModule_HackGuard();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		return $mod->isModOptEnabled()
			   && $mod->getScansCon()->AFS()->isEnabled()
			   && $opts->isRepairFileWP();
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