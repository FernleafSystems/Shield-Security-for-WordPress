<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\Afs;

class ScanEnabledAfsAutoRepairThemes extends Base {

	public const SLUG = 'scan_enabled_afs_autorepair_themes';
	public const WEIGHT = 20;

	protected function isProtected() :bool {
		$mod = $this->getCon()->getModule_HackGuard();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		/** @var Afs $scanCon */
		$scanCon = $mod->getScansCon()->getScanCon( Afs::SCAN_SLUG );
		return $mod->isModOptEnabled()
			   && $scanCon->isEnabledPluginThemeScan()
			   && $opts->isRepairFileTheme();
	}

	public function title() :string {
		return __( 'WordPress.org Theme Auto-Repair', 'wp-simple-firewall' );
	}

	public function href() :string {
		return $this->getCon()->getModule_HackGuard()->isModOptEnabled() ?
			$this->link( 'file_repair_areas' ) : $this->link( 'enable_hack_protect' );
	}

	public function descProtected() :string {
		return __( 'Auto-repair of files from WordPress.org themes is enabled.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Auto-repair of files from WordPress.org themes isn't enabled.", 'wp-simple-firewall' );
	}
}