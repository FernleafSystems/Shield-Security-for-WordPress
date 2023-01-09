<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\Afs;

class ScanEnabledAfsAutoRepairCore extends Base {

	public const SLUG = 'scan_enabled_afs_autorepair_core';

	protected function isProtected() :bool {
		$mod = $this->getCon()->getModule_HackGuard();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		return $mod->isModOptEnabled()
			   && $mod->getScansCon()->getScanCon( Afs::SCAN_SLUG )->isEnabled()
			   && $opts->isRepairFileWP();
	}

	public function title() :string {
		return __( 'WordPress Core Auto-Repair', 'wp-simple-firewall' );
	}

	public function href() :string {
		return $this->getCon()->getModule_HackGuard()->isModOptEnabled() ?
			$this->link( 'file_repair_areas' ) : $this->link( 'enable_hack_protect' );
	}

	public function descProtected() :string {
		return __( 'Auto-repair of modified WordPress core files is enabled.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Auto-repair of modified WordPress core files isn't enabled.", 'wp-simple-firewall' );
	}
}