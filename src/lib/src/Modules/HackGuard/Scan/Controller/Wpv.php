<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Utilities\WpvAddPluginRows;
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
			( new WpvAddPluginRows() )->execute();
		}, 10, 2 );

		if ( $this->isAutoupdatesEnabled() ) {
			add_filter( 'auto_update_plugin', [ $this, 'autoupdateVulnerablePlugins' ], \PHP_INT_MAX, 2 );
		}
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

	public function getAdminMenuItems() :array {
		$items = [];
		$status = $this->mod()->getScansCon()->getScanResultsCount();

		$template = [
			'id'    => self::con()->prefix( 'problems-'.$this->getSlug() ),
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
	 * @param bool|mixed       $doAutoUpdate
	 * @param \stdClass|string $mItem
	 */
	public function autoupdateVulnerablePlugins( $doAutoUpdate, $mItem ) :bool {
		$itemFile = Services::WpGeneral()->getFileFromAutomaticUpdateItem( $mItem );
		return $doAutoUpdate || $this->hasVulnerabilities( $itemFile );
	}

	public function hasVulnerabilities( string $file ) :bool {
		return \count( $this->getResultsForDisplay()->getItemsForSlug( $file ) ) > 0;
	}

	public function isAutoupdatesEnabled() :bool {
		return $this->opts()->isOpt( 'wpvuln_scan_autoupdate', 'Y' );
	}

	protected function newItemActionHandler() :Scans\Wpv\Utilities\ItemActionHandler {
		return new Scans\Wpv\Utilities\ItemActionHandler();
	}

	public function isCronAutoRepair() :bool {
		return $this->isAutoupdatesEnabled();
	}

	public function isEnabled() :bool {
		$con = self::con();
		return $con->comps !== null
			   && $con->comps->opts_lookup->optIsAndModForOptEnabled( 'enable_core_file_integrity_scan', 'Y' )
			   && !$this->isRestricted();
	}

	public function buildScanAction() :Scans\Wpv\ScanActionVO {
		return ( new Scans\Wpv\BuildScanAction() )
			->setScanActionVO( $this->getScanActionVO() )
			->build()
			->getScanActionVO();
	}
}