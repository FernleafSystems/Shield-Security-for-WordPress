<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers;
use FernleafSystems\Wordpress\Services\Core\VOs\WpPluginVo;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg;

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
			$oLocator = new File\LocateStrInFile();
			foreach ( $oDirIt as $oFsItem ) {
				/** @var \SplFileInfo $oFsItem */
				$sFullPath = wp_normalize_path( $oFsItem->getPathname() );

				$sContent = $oFs->getFileContent( $sFullPath );

				if ( !empty( $sContent ) ) {
					foreach ( $aSigs as $sSig ) {

						$aLines = $oLocator->setNeedle( $sSig )
										   ->inFileContent( $sContent );
						if ( !empty( $aLines ) ) {

							if ( $this->canExcludeFile( $sFullPath ) ) {
								continue;
							}

							$oResultItem = new ResultItem();
							$oResultItem->path_full = wp_normalize_path( $sFullPath );
							$oResultItem->path_fragment = str_replace( wp_normalize_path( ABSPATH ), '', $oResultItem->path_full );
							$oResultItem->is_mal = true;
							$oResultItem->mal_sig = base64_encode( $sSig );
							$oResultItem->file_lines = $aLines;
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
	 * @param string $sFullPath - normalized
	 * @return bool
	 */
	private function canExcludeFile( $sFullPath ) {
		return $this->isValidCoreFile( $sFullPath ) || $this->isPluginFileValid( $sFullPath );
	}

	/**
	 * @param string $sFullPath - normalized
	 * @return bool
	 */
	private function isPluginFileValid( $sFullPath ) {
		$bCanExclude = false;

		if ( strpos( $sFullPath, wp_normalize_path( WP_PLUGIN_DIR ) ) === 0 ) {

			$oPluginFiles = new WpOrg\Plugin\Files();
			$oThePlugin = $oPluginFiles->findPluginFromFile( $sFullPath );
			if ( $oThePlugin instanceof WpPluginVo ) {
				try {
					$sTmpFile = $oPluginFiles
						->setWorkingSlug( $oThePlugin->slug )
						->setWorkingVersion( $oThePlugin->Version )
						->getOriginalFileFromVcs( $sFullPath );
					if ( Services::WpFs()->exists( $sTmpFile )
						 && ( new File\Compare\CompareHash() )->isEqualFilesMd5( $sTmpFile, $sFullPath ) ) {
						$bCanExclude = true;
					}
				}
				catch ( \Exception $oE ) {
				}
			}
		}

		return $bCanExclude;
	}

	/**
	 * @param string $sFullPath
	 * @return bool
	 */
	private function isValidCoreFile( $sFullPath ) {
		$sCoreHash = Services::CoreFileHashes()->getFileHash( $sFullPath );
		try {
			$bValid = !empty( $sCoreHash )
					  && ( new File\Compare\CompareHash() )->isEqualFileMd5( $sFullPath, $sCoreHash );
		}
		catch ( \Exception $oE ) {
			$bValid = false;
		}
		return $bValid;
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