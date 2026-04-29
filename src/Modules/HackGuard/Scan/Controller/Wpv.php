<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Utilities\WpvAddPluginRows;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class Wpv extends BaseForAssets {

	public const SCAN_SLUG = 'wpv';

	protected function run() {
		parent::run();

		add_action( 'upgrader_process_complete', fn() => $this->scheduleOnDemandScan(), 10, 0 );
		add_action( 'deleted_plugin', fn() => $this->scheduleOnDemandScan(), 10, 0 );
		add_action( 'load-plugins.php', fn() => ( new WpvAddPluginRows() )->execute(), 10, 0 );
	}

	/**
	 * @return array{name:string, subtitle:string}
	 */
	public function getStrings() :array {
		return [
			'name'     => __( 'Vulnerabilities', 'wp-simple-firewall' ),
			'subtitle' => __( "Be alerted to plugins and themes with known security vulnerabilities", 'wp-simple-firewall' ),
		];
	}

	public function getQueueGroupSize() :int {
		return 10;
	}

	public function hasVulnerabilities( string $file ) :bool {
		return \count( $this->getResultsForDisplay()->getItemsForSlug( $file ) ) > 0;
	}

	public function isAutoupdatesEnabled() :bool {
		return self::con()->opts->optIs( 'wpvuln_scan_autoupdate', 'Y' );
	}

	protected function newItemActionHandler() :Scans\Wpv\Utilities\ItemActionHandler {
		return new Scans\Wpv\Utilities\ItemActionHandler();
	}

	public function isCronAutoRepair() :bool {
		return $this->isAutoupdatesEnabled();
	}

	public function isEnabled() :bool {
		return self::con()->opts->optIs( 'enable_wpvuln_scan', 'Y' )
			   && !$this->isRestricted()
			   && self::con()->caps->canScanVulnerabilities();
	}

	public function buildScanAction( ?Scans\Base\BaseScanActionVO $scanAction = null ) :Scans\Wpv\ScanActionVO {
		return ( new Scans\Wpv\BuildScanAction() )
			->setScanActionVO( $scanAction ?? $this->newScanActionVO() )
			->build()
			->getScanActionVO();
	}
}
