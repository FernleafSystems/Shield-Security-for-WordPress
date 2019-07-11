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
	private $aMalSigsRegex;

	/**
	 * @var string[]
	 */
	private $aMalSigsSimple;

	/**
	 * @var string[]
	 */
	private $aWhitelistPaths;

	/**
	 * @return ResultsSet
	 */
	public function run() {
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

			$oLocator = new File\LocateStrInFile();
			foreach ( $oDirIt as $oFsItem ) {
				$sFullPath = wp_normalize_path( $oFsItem->getPathname() );
				/** @var \SplFileInfo $oFsItem */
				if ( $this->isWhitelistedPath( $sFullPath ) || $oFsItem->getSize() == 0 ) {
					continue;
				}

				$oLocator->setPath( $oFsItem->getPathname() );

				$oLocator->setIsRegEx( false );
				foreach ( $this->getMalSigsSimple() as $sSig ) {

					$aLines = $oLocator->setNeedle( $sSig )
									   ->run();
					if ( !empty( $aLines ) && !$this->canExcludeFile( $sFullPath ) ) {
						$oResultSet->addItem( $this->getResultItemFromLines( $aLines, $sFullPath, $sSig ) );
						continue( 2 );
					}
				}

				$oLocator->setIsRegEx( true );
				foreach ( $this->getMalSigsRegex() as $sSig ) {

					$aLines = $oLocator->setNeedle( $sSig )
									   ->run();
					if ( !empty( $aLines ) && !$this->canExcludeFile( $sFullPath ) ) {
						$oResultSet->addItem( $this->getResultItemFromLines( $aLines, $sFullPath, $sSig ) );
						continue( 2 );
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
	 * @param $aLines
	 * @param $sFullPath
	 * @param $sSig
	 * @return ResultItem
	 */
	private function getResultItemFromLines( $aLines, $sFullPath, $sSig ) {
		$oResultItem = new ResultItem();
		$oResultItem->path_full = wp_normalize_path( $sFullPath );
		$oResultItem->path_fragment = str_replace( wp_normalize_path( ABSPATH ), '', $oResultItem->path_full );
		$oResultItem->is_mal = true;
		$oResultItem->mal_sig = base64_encode( $sSig );
		$oResultItem->file_lines = $aLines;
		return $oResultItem;
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
	public function getMalSigsRegex() {
		return $this->aMalSigsRegex;
	}

	/**
	 * @return string[]
	 */
	public function getMalSigsSimple() {
		return $this->aMalSigsSimple;
	}

	/**
	 * @return string[]
	 */
	public function getWhitelistedPaths() {
		return is_array($this->aWhitelistPaths) ? $this->aWhitelistPaths : [];
	}

	/**
	 * @param string $sThePath
	 * @return bool
	 */
	public function isWhitelistedPath( $sThePath ) {
		$bWhitelisted = false;
		foreach ( $this->getWhitelistedPaths() as $sWlPath ) {
			if ( stripos( $sThePath, $sWlPath ) === 0 ) {
				$bWhitelisted = true;
				break;
			}
		}
		return $bWhitelisted;
	}

	/**
	 * @param string[] $aSigs
	 * @return $this
	 */
	public function setMalSigsRegex( $aSigs ) {
		$this->aMalSigsRegex = $aSigs;
		return $this;
	}
	/**
	 * @param string[] $aSigs
	 * @return $this
	 */
	public function setMalSigsSimple( $aSigs ) {
		$this->aMalSigsSimple = $aSigs;
		return $this;
	}


	/**
	 * @param string[] $aSigs
	 * @return $this
	 */
	public function setWhitelistedPaths( $aSigs ) {
		$this->aWhitelistPaths = $aSigs;
		return $this;
	}
}