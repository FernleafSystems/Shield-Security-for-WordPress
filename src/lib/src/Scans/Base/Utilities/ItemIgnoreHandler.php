<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Common\ScanItemConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ItemIgnoreHandler {

	use ModConsumer;
	use ScanItemConsumer;

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function ignore() :bool {
		$item = $this->getScanItem();
		if ( empty( $item->VO ) ) {
			throw new \Exception( 'Item could not be found to ignore.' );
		}

		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		$updated = $mod->getDbH_ResultItems()
					   ->getQueryUpdater()
					   ->updateById( $item->VO->resultitem_id, [
						   'ignored_at' => Services::Request()->ts()
					   ] );
		if ( !$updated ) {
			throw new \Exception( 'Item could not be ignored at this time.' );
		}

		return true;
	}
}