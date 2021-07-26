<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Reports\ScanRepairs;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class Repair
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc
 */
class Repair extends Scans\Base\Utilities\BaseRepair {

	/**
	 * @inheritDoc
	 */
	public function repairItem() :bool {
		throw new \Exception( 'Repair action is not supported' );
	}

	public function deleteItem() :bool {
		/** @var Ufc\ResultItem $item */
		$item = $this->getScanItem();

		$coreHashes = Services::CoreFileHashes();
		if ( $coreHashes->isCoreFile( $item->path_fragment ) ) {
			throw new \Exception( sprintf( 'File "%s" is an official WordPress core file.', $item->path_fragment ) );
		}

		$FS = Services::WpFs();
		return !$FS->isFile( $item->path_full ) || (bool)$FS->deleteFile( $item->path_full );
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function canRepair() :bool {
		/** @var Ufc\ResultItem $item */
		$item = $this->getScanItem();
		return (bool)Services::WpFs()->exists( $item->path_full );
	}
}