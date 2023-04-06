<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ScanResults;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

class Wpv extends BaseForAssets {

	public const SCAN_SLUG = 'wpv';

	protected function run() {
		parent::run();

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

		if ( $this->opts()->isWpvulnAutoupdatesEnabled() ) {
			add_filter( 'auto_update_plugin', [ $this, 'autoupdateVulnerablePlugins' ], PHP_INT_MAX, 2 );
		}
	}

	public function getAdminMenuItems() :array {
		$items = [];
		$status = $this->getScansController()->getScanResultsCount();

		$template = [
			'id'    => $this->getCon()->prefix( 'problems-'.$this->getSlug() ),
			'title' => '<div class="wp-core-ui wp-ui-notification shield-counter"><span aria-hidden="true">%s</span></div>',
		];

		$count = $status->countVulnerableAssets();
		if ( $count > 0 ) {
			$warning = $template;
			$warning[ 'id' ] .= '-wpv';
			$warning[ 'title' ] = __( 'Vulnerable Plugins', 'wp-simple-firewall' ).sprintf( $warning[ 'title' ], $count );
			$warning[ 'warnings' ] = $count;
			$items[] = $warning;
		}

		return $items;
	}

	/**
	 * @param bool             $doAutoUpdate
	 * @param \stdClass|string $mItem
	 */
	public function autoupdateVulnerablePlugins( $doAutoUpdate, $mItem ) :bool {
		$itemFile = Services::WpGeneral()->getFileFromAutomaticUpdateItem( $mItem );
		return $doAutoUpdate || $this->hasVulnerabilities( $itemFile );
	}

	public function hasVulnerabilities( string $file ) :bool {
		return count( $this->getResultsForDisplay()->getItemsForSlug( $file ) ) > 0;
	}

	/**
	 * @return Scans\Wpv\Utilities\ItemActionHandler
	 */
	protected function newItemActionHandler() {
		return new Scans\Wpv\Utilities\ItemActionHandler();
	}

	public function isCronAutoRepair() :bool {
		return $this->opts()->isWpvulnAutoupdatesEnabled();
	}

	public function isEnabled() :bool {
		return $this->getCon()->isPremiumActive() && $this->opts()->isOpt( 'enable_wpvuln_scan', 'Y' );
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