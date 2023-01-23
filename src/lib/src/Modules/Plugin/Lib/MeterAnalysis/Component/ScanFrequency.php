<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Options;

class ScanFrequency extends Base {

	public const SLUG = 'scan_frequency';
	public const WEIGHT = 10;

	protected function isProtected() :bool {
		$mod = $this->getCon()->getModule_HackGuard();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		return $mod->isModOptEnabled() && $opts->getScanFrequency() > 1;
	}

	public function title() :string {
		return __( 'Scanning Frequency', 'wp-simple-firewall' );
	}

	public function href() :string {
		return $this->getCon()->getModule_HackGuard()->isModOptEnabled() ?
			$this->link( 'scan_frequency' ) : $this->link( 'enable_hack_protect' );
	}

	public function descProtected() :string {
		return __( 'Scans are run on your site at least twice per day.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Scans are run on your site only once per day.", 'wp-simple-firewall' );
	}
}