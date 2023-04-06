<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ResultItems;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class Apc extends BaseForAssets {

	public const SCAN_SLUG = 'apc';

	public function getAdminMenuItems() :array {
		$items = [];

		$template = [
			'id'    => $this->getCon()->prefix( 'problems-'.$this->getSlug() ),
			'title' => '<div class="wp-core-ui wp-ui-notification shield-counter"><span aria-hidden="true">%s</span></div>',
		];

		$count = $this->getScansController()->getScanResultsCount()->countAbandoned();
		if ( $count > 0 ) {
			$warning = $template;
			$warning[ 'id' ] .= '-apc';
			$warning[ 'title' ] = __( 'Abandoned Plugins', 'wp-simple-firewall' ).sprintf( $warning[ 'title' ], $count );
			$warning[ 'warnings' ] = $count;
			$items[] = $warning;
		}

		return $items;
	}

	public function getQueueGroupSize() :int {
		return 3;
	}

	/**
	 * @return Scans\Apc\Utilities\ItemActionHandler
	 */
	protected function newItemActionHandler() {
		return new Scans\Apc\Utilities\ItemActionHandler();
	}

	public function isEnabled() :bool {
		return $this->opts()->isOpt( 'enabled_scan_apc', 'Y' );
	}

	protected function isPremiumOnly() :bool {
		return false;
	}

	/**
	 * @return Scans\Apc\ScanActionVO
	 */
	public function buildScanAction() {
		return ( new Scans\Apc\BuildScanAction() )
			->setScanController( $this )
			->build()
			->getScanActionVO();
	}
}