<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\Compare\CompareHash;

/**
 * Class FileScanner
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf
 */
class FileScanner extends Shield\Scans\Base\Files\BaseFileScanner {

	/**
	 * @param string $fullPath
	 * @return ResultItem|null
	 */
	public function scan( string $fullPath ) {
		$results = null;
		$oHashes = Services::CoreFileHashes();

		/** @var ResultItem $item */
		$item = $this->getScanActionVO()->getNewResultItem();
		$item->path_full = $fullPath;
		$item->path_fragment = $oHashes->getFileFragment( $fullPath );
		$item->md5_file_wp = $oHashes->getFileHash( $item->path_fragment );
		$item->is_missing = !Services::WpFs()->exists( $item->path_full );
		$item->is_checksumfail = !$item->is_missing && $this->isChecksumFail( $item );
		$item->is_excluded = $this->isExcluded( $item->path_fragment )
							 || ( $item->is_missing && $this->isExcludedMissing( $item->path_fragment ) );

		if ( !$item->is_excluded && ( $item->is_missing || $item->is_checksumfail ) ) {
			$results = $item;
		}

		return $results;
	}

	/**
	 * @param $sPathFragment
	 * @return false|int
	 */
	private function isExcluded( $sPathFragment ) {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		return !empty( $oAction->exclusions_files_regex ) && preg_match( $oAction->exclusions_files_regex, $sPathFragment );
	}

	/**
	 * @param $sPathFragment
	 * @return false|int
	 */
	private function isExcludedMissing( $sPathFragment ) {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		return !empty( $oAction->exclusions_missing_regex ) && preg_match( $oAction->exclusions_missing_regex, $sPathFragment );
	}

	/**
	 * @param ResultItem $item
	 * @return bool
	 */
	private function isChecksumFail( $item ) {
		$fail = false;
		if ( !$item->is_missing ) {
			try {
				$fail = ( strpos( $item->path_full, '.php' ) > 0 )
						 && !( new CompareHash() )->isEqualFileMd5( $item->path_full, $item->md5_file_wp );
			}
			catch ( \Exception $e ) {
			}
		}
		return $fail;
	}
}