<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Common\ScanItemConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\{
	Afs\ResultItem,
	Base\FileResultItem
};
use FernleafSystems\Wordpress\Services\Services;

class ItemDeleteHandler {

	use ModConsumer;
	use ScanItemConsumer;

	/**
	 * @throws \Exception
	 */
	public function delete() :bool {
		$FS = Services::WpFs();
		$success = false;

		/** @var FileResultItem $item */
		$item = $this->getScanItem();
		if ( $this->canDelete() ) {
			$FS->deleteFile( $item->path_full );
			$success = !$FS->isFile( $item->path_full );
		}

		return $success;
	}

	protected function deleteSupported() :bool {
		return in_array( $this->getScanItem()->VO->scan, [
			Controller\Afs::SCAN_SLUG,
		] );
	}

	/**
	 * @return true
	 * @throws \Exception
	 */
	public function canDelete() :bool {
		/** @var ResultItem $item */
		$item = $this->getScanItem();

		if ( !$this->deleteSupported() ) {
			throw new \Exception( sprintf( "Deletion isn't support for scan %s", $item->VO->scan ) );
		}
		if ( !$item->is_unrecognised ) {
			throw new \Exception( sprintf( "File '%s' isn't unrecognised.", $item->path_fragment ) );
		}
		if ( !Services::WpFs()->isFile( $item->path_full ) ) {
			throw new \Exception( sprintf( "File '%s' doesn't exist.", $item->path_fragment ) );
		}

		return true;
	}
}