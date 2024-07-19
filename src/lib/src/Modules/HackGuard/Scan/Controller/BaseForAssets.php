<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseForAssets extends Base {

	/**
	 * @param Scans\Wpv\ResultItem|Scans\Apc\ResultItem $item
	 */
	public function cleanStaleResultItem( $item ) :bool {
		if ( \strpos( $item->VO->item_id, '/' ) ) {
			$asset = Services::WpPlugins()->getPluginAsVo( $item->VO->item_id );
		}
		else {
			$asset = Services::WpThemes()->getThemeAsVo( $item->VO->item_id );
		}

		$changed = false;
		if ( empty( $asset ) ) {
			/** @var \FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItems\Ops\Update $updater */
			$updater = self::con()->db_con->scan_result_items->getQueryUpdater();
			$changed = $updater->setItemDeleted( $item->VO->resultitem_id );
		}
		return $changed;
	}

	public function buildScanResult( array $rawResult ) :\FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItems\Ops\Record {
		/** @var \FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItems\Ops\Record $record */
		$record = self::con()->db_con->scan_result_items->getRecord();
		$record->item_id = $rawResult[ 'slug' ];
		$record->item_type = \strpos( $rawResult[ 'slug' ], '/' ) ?
			\FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItems\Ops\Handler::ITEM_TYPE_PLUGIN :
			\FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItems\Ops\Handler::ITEM_TYPE_THEME;

		unset( $rawResult[ 'context' ] );
		unset( $rawResult[ 'hash' ] );
		unset( $rawResult[ 'slug' ] );
		$record->meta = $rawResult;
		return $record;
	}
}