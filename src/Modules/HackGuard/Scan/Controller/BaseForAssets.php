<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseForAssets extends Base {

	/**
	 * @param Scans\Wpv\ResultItem|Scans\Apc\ResultItem $item
	 */
	public function cleanStaleResultItem( $item ) :bool {
		if ( $item->VO->item_type === \FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItems\Ops\Handler::ITEM_TYPE_PLUGIN ) {
			$asset = Services::WpPlugins()->getPluginAsVo( $item->VO->item_id );
		}
		else {
			$asset = Services::WpThemes()->getThemeAsVo( $item->VO->item_id );
		}

		$changed = false;
		if ( empty( $asset ) ) {
			/** @var \FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItems\Ops\Update $updater */
			$updater = self::con()->db_con->scan_result_items->getQueryUpdater();
			$changed = $updater->setItemAssetReplaced( $item->VO->resultitem_id );
		}
		return $changed;
	}

	public function buildScanResult( array $rawResult ) :\FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItems\Ops\Record {
		/** @var \FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItems\Ops\Record $record */
		$record = self::con()->db_con->scan_result_items->getRecord();
		$isPlugin = !empty( Services::WpPlugins()->getPluginAsVo( $rawResult[ 'slug' ] ?? '' ) );
		$record->asset_key = (string)( $rawResult[ 'slug' ] ?? '' );
		$record->asset_type = $isPlugin ? 'plugin' : 'theme';
		$record->item_id = $rawResult[ 'slug' ];
		$record->item_type = $isPlugin ?
			\FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItems\Ops\Handler::ITEM_TYPE_PLUGIN :
			\FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItems\Ops\Handler::ITEM_TYPE_THEME;
		$record->last_seen_at = Services::Request()->ts();
		$record->resolved_at = 0;
		$record->resolution_reason = '';
		$record->scan = $this->getSlug();

		unset( $rawResult[ 'context' ] );
		unset( $rawResult[ 'hash' ] );
		unset( $rawResult[ 'slug' ] );
		$record->meta = $rawResult;
		return $record;
	}
}
