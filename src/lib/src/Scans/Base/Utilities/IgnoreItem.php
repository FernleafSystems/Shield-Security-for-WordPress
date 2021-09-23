<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Common\ScanItemConsumer;
use FernleafSystems\Wordpress\Services\Services;

class IgnoreItem {

	use ModConsumer;
	use ScanItemConsumer;

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function ignore() :bool {
		if ( empty( $this->getScanItem()->VO ) ) {
			throw new \Exception( 'Item could not be found to ignore.' );
		}

		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		$updated = $mod->getDbHandler_ScanResults()
					   ->getQueryUpdater()
					   ->setUpdateWheres( [
						   'hash' => $this->getScanItem()->hash
					   ] )
					   ->setUpdateData( [
						   'ignored_at' => Services::Request()->ts()
					   ] )
					   ->query();
		if ( !$updated ) {
			throw new \Exception( 'Item could not be ignored at this time.' );
		}

		return true;
	}
}