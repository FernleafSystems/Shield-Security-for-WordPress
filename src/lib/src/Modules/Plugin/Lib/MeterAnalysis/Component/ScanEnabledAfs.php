<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class ScanEnabledAfs extends ScanEnabledBase {

	use Traits\OptConfigBased;

	public const SLUG = 'scan_enabled_afs';

	protected function getOptConfigKey() :string {
		return 'enable_core_file_integrity_scan';
	}

	public function title() :string {
		return __( 'WordPress File Scanner', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'WordPress files are protected against tampering.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "WordPress files aren't scanned for tampering.", 'wp-simple-firewall' );
	}
}