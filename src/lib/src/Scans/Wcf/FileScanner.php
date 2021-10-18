<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\Compare\CompareHash;

class FileScanner extends Shield\Scans\Base\Files\BaseFileScanner {

	/**
	 * @param string $fullPath
	 * @return ResultItem|null
	 */
	public function scan( string $fullPath ) {
		$resultItem = null;

		/** @var ResultItem $item */
		$item = $this->getScanController()->getNewResultItem();
		$item->path_full = $fullPath;
		$item->path_fragment = Services::CoreFileHashes()->getFileFragment( $fullPath );
		$item->is_checksumfail = false;

		if ( !$this->isExcluded( $item->path_fragment ) ) {

			$exists = Services::WpFs()->exists( $item->path_full );

			$item->is_missing = !$exists && !$this->isExcludedMissing( $item->path_fragment );
			$item->is_checksumfail = $exists && $this->isChecksumFail( $item );

			if ( $item->is_missing || $item->is_checksumfail ) {
				$resultItem = $item;
			}
		}

		return $resultItem;
	}

	private function isExcluded( string $pathFragment ) :bool {
		/** @var Shield\Modules\HackGuard\Scan\Controller\Wcf $scanCon */
		$scanCon = $this->getScanController();
		$exclusionsRegex = $scanCon->getScanFileExclusions();
		return !empty( $exclusionsRegex ) && preg_match( $exclusionsRegex, $pathFragment );
	}

	private function isExcludedMissing( string $pathFragment ) :bool {
		/** @var Shield\Modules\HackGuard\Scan\Controller\Wcf $scanCon */
		$scanCon = $this->getScanController();
		$exclusionsRegex = $scanCon->getScanExclusionsForMissingItems();
		return !empty( $exclusionsRegex ) && preg_match( $exclusionsRegex, $pathFragment );
	}

	private function isChecksumFail( ResultItem $item ) :bool {
		try {
			$fail = ( strpos( $item->path_full, '.php' ) > 0 )
					&&
					!( new CompareHash() )->isEqualFile(
						$item->path_full,
						Services::CoreFileHashes()->getFileHash( $item->path_fragment )
					);
		}
		catch ( \Exception $e ) {
			$fail = false;
		}
		return $fail;
	}
}