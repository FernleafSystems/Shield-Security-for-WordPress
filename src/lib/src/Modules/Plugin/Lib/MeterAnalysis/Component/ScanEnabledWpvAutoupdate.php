<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\Wpv;

class ScanEnabledWpvAutoupdate extends Base {

	public const SLUG = 'scan_enabled_wpv_autoupdate';

	protected function isProtected() :bool {
		$mod = $this->getCon()->getModule_HackGuard();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		return $mod->isModOptEnabled()
			   && $mod->getScansCon()->getScanCon( Wpv::SCAN_SLUG )->isEnabled()
			   && $opts->isWpvulnAutoupdatesEnabled();
	}

	public function title() :string {
		return __( 'Auto-Update Vulnerable Plugins', 'wp-simple-firewall' );
	}

	public function href() :string {
		return $this->getCon()->getModule_HackGuard()->isModOptEnabled() ?
			$this->link( 'wpvuln_scan_autoupdate' ) : $this->link( 'enable_hack_protect' );
	}

	public function descProtected() :string {
		return __( 'Plugins with known vulnerabilities are automatically updated to protect your site.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Plugins with known vulnerabilities aren't automatically updated to protect your site.", 'wp-simple-firewall' );
	}
}