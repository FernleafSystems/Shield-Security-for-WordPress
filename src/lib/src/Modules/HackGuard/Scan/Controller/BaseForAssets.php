<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ResultItems;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseForAssets extends Base {

	/**
	 * @param Scans\Wpv\ResultItem|Scans\Apc\ResultItem $item
	 */
	public function cleanStaleResultItem( $item ) {
		if ( \strpos( $item->VO->item_id, '/' ) ) {
			$asset = Services::WpPlugins()->getPluginAsVo( $item->VO->item_id );
		}
		else {
			$asset = Services::WpThemes()->getThemeAsVo( $item->VO->item_id );
		}

		if ( empty( $asset ) ) {
			/** @var ResultItems\Ops\Update $updater */
			$updater = $this->mod()->getDbH_ResultItems()->getQueryUpdater();
			$updater->setItemDeleted( $item->VO->resultitem_id );
		}
	}

	public function buildScanResult( array $rawResult ) :ResultItems\Ops\Record {
		/** @var ResultItems\Ops\Record $record */
		$record = $this->mod()->getDbH_ResultItems()->getRecord();
		$record->item_id = $rawResult[ 'slug' ];
		$record->item_type = \strpos( $rawResult[ 'slug' ], '/' ) ?
			ResultItems\Ops\Handler::ITEM_TYPE_PLUGIN :
			ResultItems\Ops\Handler::ITEM_TYPE_THEME;

		unset( $rawResult[ 'context' ] );
		unset( $rawResult[ 'hash' ] );
		unset( $rawResult[ 'slug' ] );
		$record->meta = $rawResult;
		return $record;
	}
}