<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Options;

class ScanEnabledWpvAutoupdate extends Base {

	use Traits\OptConfigBased;

	public const SLUG = 'scan_enabled_wpv_autoupdate';

	protected function getOptConfigKey() :string {
		return 'wpvuln_scan_autoupdate';
	}

	protected function testIfProtected() :bool {
		$mod = $this->getCon()->getModule_HackGuard();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		return $mod->isModOptEnabled()
			   && $opts->isWpvulnAutoupdatesEnabled()
			   && $mod->getScansCon()
					  ->WPV()
					  ->isEnabled();
	}

	public function title() :string {
		return __( 'Auto-Update Vulnerable Plugins', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'Plugins with known vulnerabilities are automatically updated to protect your site.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Plugins with known vulnerabilities aren't automatically updated to protect your site.", 'wp-simple-firewall' );
	}
}