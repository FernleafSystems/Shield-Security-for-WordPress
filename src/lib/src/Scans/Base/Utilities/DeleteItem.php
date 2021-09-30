<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Common\ScanItemConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\{
	Base\FileResultItem,
	Mal,
	Ptg,
	Ufc
};
use FernleafSystems\Wordpress\Services\Services;

class DeleteItem {

	use ModConsumer;
	use ScanItemConsumer;

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function delete() :bool {
		$FS = Services::WpFs();
		$success = false;

		$supportedFileScans = [
			Controller\Mal::SCAN_SLUG,
			Controller\Ufc::SCAN_SLUG,
			Controller\Ptg::SCAN_SLUG
		];

		/** @var FileResultItem $item */
		$item = $this->getScanItem();
		if ( in_array( $item->VO->scan, $supportedFileScans ) ) {
			$FS->deleteFile( $item->path_full );
			$success = !$FS->isFile( $item->path_full );
		}

		return $success;
	}

	/**
	 * @return true
	 * @throws \Exception
	 */
	public function canDelete() :bool {
		$FS = Services::WpFs();
		$item = $this->getScanItem();

		switch ( $item->VO->scan ) {
			case Controller\Mal::SCAN_SLUG:
				/** @var Mal\ResultItem $item */
				if ( !$FS->isFile( $item->path_full ) ) {
					throw new \Exception( sprintf( "File '%s' doesn't exist.", $item->path_fragment ) );
				}
				break;

			case Controller\Ufc::SCAN_SLUG:
				/** @var Ufc\ResultItem $item */
				if ( !$FS->isFile( $item->path_full ) ) {
					throw new \Exception( sprintf( "File '%s' doesn't exist.", $item->path_fragment ) );
				}
				$coreHashes = Services::CoreFileHashes();
				if ( $coreHashes->isCoreFile( $item->path_fragment ) ) {
					throw new \Exception( sprintf( 'File "%s" is an official WordPress core file.', $item->path_fragment ) );
				}
				break;

			case Controller\Ptg::SCAN_SLUG:
				/** @var Ptg\ResultItem $item */
				if ( !$FS->isFile( $item->path_full ) ) {
					throw new \Exception( sprintf( "File '%s' doesn't exist.", $item->path_fragment ) );
				}
				if ( !$item->is_unrecognised ) {
					throw new \Exception( sprintf( "File '%s' isn't unrecognised.", $item->path_fragment ) );
				}
				break;

			default:
				throw new \Exception( sprintf( "Deletion isn't support for scan %s", $item->VO->scan ) );
		}

		return true;
	}
}