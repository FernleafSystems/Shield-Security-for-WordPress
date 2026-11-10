<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

class Scans extends Base {

	public function tooltip() :string {
		return 'Edit settings for the entire Scans Zone';
	}

	public function components() :array {
		return [
			Component\FileScanning::class,
			Component\VulnerabilityScanning::class,
			Component\FileLocker::class,
			Component\ScanScheduling::class,
		];
	}

	public function title() :string {
		return __( 'Scans', 'wp-simple-firewall' );
	}

	protected function getUnderlyingModuleZone() :?string {
		return Component\Modules\ModuleScans::class;
	}
}
