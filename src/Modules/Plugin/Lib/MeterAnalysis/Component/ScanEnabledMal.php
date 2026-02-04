<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class ScanEnabledMal extends ScanEnabledBase {

	use Traits\OptConfigBased;

	public const MINIMUM_EDITION = 'starter';
	public const SLUG = 'scan_enabled_mal';
	public const WEIGHT = 4;

	protected function getOptConfigKey() :string {
		return 'enable_core_file_integrity_scan';
	}

	protected function testIfProtected() :bool {
		try {
			return self::con()->comps->scans->AFS()->isEnabledMalwareScanPHP();
		}
		catch ( \Exception $e ) {
			return false;
		}
	}

	public function title() :string {
		return __( 'PHP Malware Scanner', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return self::con()->caps->canScanMalwareMalai() ?
			__( 'Advanced AI PHP malware scanner is enabled.', 'wp-simple-firewall' )
			: __( "Local PHP malware scanner is enabled but AI Malware scanning is available on an upgraded plan.", 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "PHP malware scanner isn't enabled.", 'wp-simple-firewall' );
	}

	protected function score() :int {
		return self::con()->caps->canScanMalwareMalai() ? static::WEIGHT : static::WEIGHT/2;
	}
}