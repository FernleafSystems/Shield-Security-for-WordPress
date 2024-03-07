<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class Apc extends BaseForAssets {

	public const SCAN_SLUG = 'apc';

	/**
	 * @return array{name:string, subtitle:string}
	 */
	public function getStrings() :array {
		return [
			'name'     => __( 'Abandoned Plugins', 'wp-simple-firewall' ),
			'subtitle' => __( "Discover plugins that may have been abandoned by their authors", 'wp-simple-firewall' ),
		];
	}

	public function getAdminMenuItems() :array {
		$items = [];

		$template = [
			'id'    => self::con()->prefix( 'problems-'.$this->getSlug() ),
			'title' => '<div class="wp-core-ui wp-ui-notification shield-counter"><span aria-hidden="true">%s</span></div>',
		];

		$count = $this->mod()->getScansCon()->getScanResultsCount()->countAbandoned();
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
	protected function newItemActionHandler() :Scans\Apc\Utilities\ItemActionHandler {
		return new Scans\Apc\Utilities\ItemActionHandler();
	}

	public function isEnabled() :bool {
		$con = self::con();
		return $con->comps !== null && $con->comps->opts_lookup->optIsAndModForOptEnabled( 'enabled_scan_apc', 'Y' );
	}

	protected function isPremiumOnly() :bool {
		return false;
	}

	/**
	 * @throws \Exception
	 */
	public function buildScanAction() :Scans\Apc\ScanActionVO {
		return ( new Scans\Apc\BuildScanAction() )
			->setScanActionVO( $this->getScanActionVO() )
			->build()
			->getScanActionVO();
	}
}