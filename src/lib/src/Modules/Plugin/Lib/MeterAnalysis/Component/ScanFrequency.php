<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Options;

class ScanFrequency extends Base {

	use Traits\OptConfigBased;

	public const MINIMUM_EDITION = 'business';
	public const SLUG = 'scan_frequency';
	public const WEIGHT = 2;

	protected function getOptConfigKey() :string {
		return 'scan_frequency';
	}

	protected function testIfProtected() :bool {
		$mod = self::con()->getModule_HackGuard();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		return $mod->isModOptEnabled() && $opts->getScanFrequency() > 1;
	}

	public function title() :string {
		return __( 'Scanning Frequency', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'Scans are run on your site at least twice per day.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Scans are run on your site only once per day.", 'wp-simple-firewall' );
	}
}