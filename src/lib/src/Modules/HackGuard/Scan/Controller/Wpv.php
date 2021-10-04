<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ScanResults;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

class Wpv extends BaseForAssets {

	const SCAN_SLUG = 'wpv';

	protected function run() {
		parent::run();
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();

		add_action( 'upgrader_process_complete', function () {
			$this->scheduleOnDemandScan();
		}, 10, 0 );
		add_action( 'deleted_plugin', function () {
			$this->scheduleOnDemandScan();
		}, 10, 0 );
		add_action( 'load-plugins.php', function () {
			( new HackGuard\Scan\Utilities\WpvAddPluginRows() )
				->setScanController( $this )
				->execute();
		}, 10, 2 );

		if ( $opts->isWpvulnAutoupdatesEnabled() ) {
			add_filter( 'auto_update_plugin', [ $this, 'autoupdateVulnerablePlugins' ], PHP_INT_MAX, 2 );
		}
	}

	/**
	 * @param bool             $bDoAutoUpdate
	 * @param \stdClass|string $mItem
	 * @return bool
	 */
	public function autoupdateVulnerablePlugins( $bDoAutoUpdate, $mItem ) {
		$itemFile = Services::WpGeneral()->getFileFromAutomaticUpdateItem( $mItem );
		return $bDoAutoUpdate || count( $this->getPluginVulnerabilities( $itemFile ) ) > 0;
	}

	/**
	 * @param string $file
	 * @return Scans\Wpv\WpVulnDb\VulnVO[]
	 */
	public function getPluginVulnerabilities( $file ) {
		return array_map(
			function ( $item ) {
				/** @var $item Scans\Wpv\ResultItem */
				return $item->getVulnVo();
			},
			$this->getAllResults()->getItemsForSlug( $file )
		);
	}

	/**
	 * @return Scans\Wpv\Utilities\ItemActionHandler
	 */
	protected function newItemActionHandler() {
		return new Scans\Wpv\Utilities\ItemActionHandler();
	}

	public function isCronAutoRepair() :bool {
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();
		return $opts->isWpvulnAutoupdatesEnabled();
	}

	public function isEnabled() :bool {
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();
		return $opts->isPremium() && $opts->isOpt( 'enable_wpvuln_scan', 'Y' );
	}

	/**
	 * @return Scans\Wpv\ScanActionVO
	 */
	public function buildScanAction() {
		return ( new Scans\Wpv\BuildScanAction() )
			->setScanController( $this )
			->build()
			->getScanActionVO();
	}
}