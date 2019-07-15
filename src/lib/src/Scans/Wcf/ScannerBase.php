<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\Compare\CompareHash;

/**
 * Class ScannerBase
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf
 */
abstract class ScannerBase {

	use Shield\Modules\ModConsumer,
		Shield\Scans\Base\ScanActionConsumer;

	/**
	 * @return ResultsSet
	 */
	abstract public function run();

	/**
	 * @param string $sFullPath
	 * @return ResultItem|null
	 */
	protected function scanPath( $sFullPath ) {
		$oResult = null;
		$oHashes = Services::CoreFileHashes();

		$oRes = new ResultItem();
		$oRes->path_full = $sFullPath;
		$oRes->path_fragment = $oHashes->getFileFragment( $sFullPath );
		$oRes->md5_file_wp = $oHashes->getFileHash( $oRes->path_fragment );
		$oRes->is_missing = !Services::WpFs()->exists( $oRes->path_full );
		$oRes->is_checksumfail = !$oRes->is_missing && $this->isChecksumFail( $oRes );
		$oRes->is_excluded = $this->isExcluded( $oRes->path_fragment )
							 || ( $oRes->is_missing && $this->isExcludedMissing( $oRes->path_fragment ) );

		if ( !$oRes->is_excluded && ( $oRes->is_missing || $oRes->is_checksumfail ) ) {
			$oResult = $oRes;
		}

		return $oResult;
	}

	/**
	 * @param $sPathFragment
	 * @return false|int
	 */
	private function isExcluded( $sPathFragment ) {
		/** @var WcfScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		return !empty( $oAction->exclusions_files_regex ) && preg_match( $oAction->exclusions_files_regex, $sPathFragment );
	}

	/**
	 * @param $sPathFragment
	 * @return false|int
	 */
	private function isExcludedMissing( $sPathFragment ) {
		/** @var WcfScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		return !empty( $oAction->exclusions_missing_regex ) && preg_match( $oAction->exclusions_missing_regex, $sPathFragment );
	}

	/**
	 * @param ResultItem $oRes
	 * @return bool
	 */
	private function isChecksumFail( $oRes ) {
		$bFail = false;
		if ( !$oRes->is_missing ) {
			try {
				$bFail = ( strpos( $oRes->path_full, '.php' ) > 0 )
						 && !( new CompareHash() )->isEqualFileMd5( $oRes->path_full, $oRes->md5_file_wp );
			}
			catch ( \Exception $oE ) {
			}
		}
		return $bFail;
	}
}