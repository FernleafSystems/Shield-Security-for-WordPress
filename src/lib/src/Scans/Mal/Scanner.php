<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class Scanner
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal
 */
class Scanner {

	/**
	 * @var string[]
	 */
	private $aMalSigs;

	/**
	 * @return ResultsSet
	 */
	public function run() {
		$oFs = Services::WpFs();
		$oCoreHashes = Services::CoreFileHashes();
		$oResultSet = new ResultsSet();

		try {
			/**
			 * The filter handles the bulk of the file inclusions and exclusions
			 * We can set the types (extensions) of the files to include
			 * useful for the upload directory where we're only interested in JS and PHP
			 * The filter will also be responsible (in this case) for filtering out
			 * WP Core files from the collection of files to be assessed
			 */
			$oDirIt = Helpers\StandardDirectoryIterator::create( ABSPATH, 0, [ 'php', 'php5' ], false );

			$aSigs = $this->getMalSigs();
			foreach ( $oDirIt as $oFsItem ) {
				/** @var \SplFileInfo $oFsItem */
				$sFullPath = $oFsItem->getPathname();

				$sContent = $oFs->getFileContent( $sFullPath );
				if ( !empty( $sContent ) ) {
					foreach ( $aSigs as $sSig ) {
						if ( strpos( $sContent, $sSig ) !== false ) {

							// If it's a WP Core file and its hash is valid, exclude it.
							$sCoreHash = $oCoreHashes->getFileHash( $sFullPath );
							if ( !empty( $sCoreHash ) && $sCoreHash === md5_file( $sFullPath ) ) {
								continue;
							}

							$oResultItem = new ResultItem();
							$oResultItem->path_full = wp_normalize_path( $sFullPath );
							$oResultItem->path_fragment = str_replace( wp_normalize_path( ABSPATH ), '', $oResultItem->path_full );
							$oResultItem->is_mal = true;
							$oResultItem->mal_sig = $sSig;
							$oResultSet->addItem( $oResultItem );
							break;
						}
					}
				}
			}
		}
		catch ( \Exception $oE ) {
			error_log(
				sprintf( 'Shield file scanner attempted to read directory but there was error: "%s".', $oE->getMessage() )
			);
		}

		return $oResultSet;
	}

	/**
	 * @param string $sPath
	 * @return bool
	 */
	protected function canExcludeFile( $sPath ) {
		$bCanExclude = false;
		$oCoreHashes = Services::CoreFileHashes();


		return $bCanExclude;
	}

	/**
	 * @return string[]
	 */
	public function getMalSigs() {
		return $this->aMalSigs;
	}

	/**
	 * @param string[] $sFilePathMalSigs
	 * @return $this
	 */
	public function setMalSigs( $sFilePathMalSigs ) {
		$this->aMalSigs = $sFilePathMalSigs;
		return $this;
	}
}