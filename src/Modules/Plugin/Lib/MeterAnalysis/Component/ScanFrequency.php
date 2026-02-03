<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class ScanFrequency extends Base {

	use Traits\OptConfigBased;

	public const MINIMUM_EDITION = 'business';
	public const SLUG = 'scan_frequency';
	public const WEIGHT = 2;

	protected function getOptConfigKey() :string {
		return 'scan_frequency';
	}

	protected function testIfProtected() :bool {
		return self::con()->opts->optGet( 'scan_frequency' ) > 1;
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