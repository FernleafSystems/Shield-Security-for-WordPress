<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ResultItems;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseForAssets extends Base {

	/**
	 * @param Scans\Ptg\ResultItem|Scans\Wpv\ResultItem|Scans\Apc\ResultItem $item
	 * @return bool
	 */
	protected function isResultItemStale( $item ) :bool {
		if ( $item->context == 'plugins' ) {
			$asset = Services::WpPlugins()->getPluginAsVo( $item->slug );
			$stale = empty( $asset );
		}
		else {
			$asset = Services::WpThemes()->getThemeAsVo( $item->slug );
			$stale = empty( $asset );
		}
		return $stale;
	}

	public function buildScanResult( array $rawResult ) :ResultItems\Ops\Record {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var ResultItems\Ops\Record $record */
		$record = $mod->getDbH_ResultItems()->getRecord();
		$record->meta = $rawResult;
		$record->hash = $rawResult[ 'hash' ];
		$record->item_id = $rawResult[ 'slug' ];
		$record->item_type = $rawResult[ 'context' ] === 'plugins' ?
			ResultItems\Ops\Handler::ITEM_TYPE_PLUGIN :
			ResultItems\Ops\Handler::ITEM_TYPE_THEME;
		return $record;
	}
}