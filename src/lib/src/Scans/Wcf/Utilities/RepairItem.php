<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\{
	Base\Utilities\RepairItemBase,
	Helpers\WpCoreFile,
	Wcf\ResultItem
};
use FernleafSystems\Wordpress\Services\Services;

class RepairItem extends RepairItemBase {

	public function repairItem() :bool {
		/** @var ResultItem $item */
		$item = $this->getScanItem();
		$path = trim( wp_normalize_path( $item->path_fragment ), '/' );
		return ( new WpCoreFile() )->replace( $path );
	}

	public function canRepair() :bool {
		/** @var ResultItem $item */
		$item = $this->getScanItem();
		return Services::CoreFileHashes()->isCoreFile( $item->path_fragment );
	}
}