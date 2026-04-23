<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Common\ScanItemConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ItemIgnoreHandler {

	use PluginControllerConsumer;
	use ScanItemConsumer;

	/**
	 * @throws \Exception
	 */
	public function ignore() :bool {
		$item = $this->getScanItem();
		if ( empty( $item->VO ) ) {
			throw new \Exception( 'Item could not be found to ignore.' );
		}

		$updated = self::con()
			->db_con
			->scan_result_items
			->getQueryUpdater()
			->updateById( $item->VO->resultitem_id, [
				'ignored_at' => Services::Request()->ts()
			] );
		if ( !$updated ) {
			throw new \Exception( 'Item could not be ignored at this time.' );
		}

		return true;
	}

	/**
	 * @throws \Exception
	 */
	public function unignore() :bool {
		$item = $this->getScanItem();
		if ( empty( $item->VO ) ) {
			throw new \Exception( 'Item could not be found to restore.' );
		}

		if ( $item->VO->ignored_at === 0 ) {
			return true;
		}

		$updated = self::con()
			->db_con
			->scan_result_items
			->getQueryUpdater()
			->updateById( $item->VO->resultitem_id, [
				'ignored_at' => 0,
			] );
		if ( !$updated ) {
			throw new \Exception( 'Item could not be restored at this time.' );
		}

		return true;
	}
}
