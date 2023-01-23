<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\Afs;

class ScanEnabledAfsAutoRepairThemes extends Base {

	use Traits\OptConfigBased;

	public const SLUG = 'scan_enabled_afs_autorepair_themes';
	public const WEIGHT = 2;

	protected function getOptConfigKey() :string {
		return 'file_repair_areas';
	}

	protected function testIfProtected() :bool {
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

	public function descProtected() :string {
		return __( 'Auto-repair of files from WordPress.org themes is enabled.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Auto-repair of files from WordPress.org themes isn't enabled.", 'wp-simple-firewall' );
	}
}