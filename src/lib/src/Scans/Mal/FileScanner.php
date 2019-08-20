<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Core\VOs\WpPluginVo;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities;

/**
 * Class FileScanner
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal
 */
class FileScanner extends Shield\Scans\Base\Files\BaseFileScanner {

	/**
	 * @param string $sFullPath
	 * @return ResultItem|null
	 */
	public function scan( $sFullPath ) {
		$oResultItem = null;

		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();

		try {
			$oLocator = ( new Utilities\File\LocateStrInFile() )->setPath( $sFullPath );

			{ // Simple Patterns first
				$oLocator->setIsRegEx( false );
				foreach ( $oAction->patterns_simple as $sSig ) {

					$aLines = $oLocator->setNeedle( $sSig )
									   ->run();
					if ( !empty( $aLines ) && !$this->canExcludeFile( $sFullPath ) ) {
						return $this->getResultItemFromLines( $aLines, $sFullPath, $sSig );
					}
				}
			}

			{ // RegEx Patterns
				$oLocator->setIsRegEx( true );
				foreach ( $oAction->patterns_regex as $sSig ) {

					$aLines = $oLocator->setNeedle( $sSig )
									   ->run();
					if ( !empty( $aLines ) && !$this->canExcludeFile( $sFullPath ) ) {
						return $this->getResultItemFromLines( $aLines, $sFullPath, $sSig );
					}
				}
			}
		}
		catch ( \Exception $oE ) {
		}

		return $oResultItem;
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
		return $this->isValidCoreFile( $sFullPath ) || $this->isPluginFileValid( $sFullPath )
			   || $this->isPathWhitelisted( $sFullPath );
	}

	/**
	 * @param string $sFullPath
	 * @return bool
	 */
	private function isPathWhitelisted( $sFullPath ) {
		$bWhitelisted = false;
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		if ( isset( $oAction->whitelist_hashes[ basename( $sFullPath ) ] ) ) {
			try {
				$oHasher = new Utilities\File\Compare\CompareHash();
				foreach ( $oAction->whitelist_hashes[ basename( $sFullPath ) ] as $sWlHash ) {
					if ( $oHasher->isEqualFileSha1( $sFullPath, $sWlHash ) ) {
						$bWhitelisted = true;
						break;
					}
				}
			}
			catch ( \InvalidArgumentException $oE ) {
			}
		}
		return $bWhitelisted;
	}

	/**
	 * @param string $sFullPath - normalized
	 * @return bool
	 */
	private function isPluginFileValid( $sFullPath ) {
		$bCanExclude = false;

		if ( strpos( $sFullPath, wp_normalize_path( WP_PLUGIN_DIR ) ) === 0 ) {

			$oPluginFiles = new Utilities\WpOrg\Plugin\Files();
			$oThePlugin = $oPluginFiles->findPluginFromFile( $sFullPath );
			if ( $oThePlugin instanceof WpPluginVo ) {

				$oPlugVersion = ( new Utilities\WpOrg\Plugin\Versions() )
					->setWorkingSlug( $oThePlugin->slug )
					->setWorkingVersion( $oThePlugin->Version );

				// Only try to download load a file if the plugin actually uses SVN Tags.
				if ( $oPlugVersion->getWhetherLatestUsesSvnTag() ) {
					try {
						$sTmpFile = $oPluginFiles
							->setWorkingSlug( $oThePlugin->slug )
							->setWorkingVersion( $oThePlugin->Version )
							->getOriginalFileFromVcs( $sFullPath );
						if ( Services::WpFs()->exists( $sTmpFile )
							 && ( new Utilities\File\Compare\CompareHash() )->isEqualFilesMd5( $sTmpFile, $sFullPath ) ) {
							$bCanExclude = true;
						}
					}
					catch ( \Exception $oE ) {
					}
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
					  && ( new Utilities\File\Compare\CompareHash() )->isEqualFileMd5( $sFullPath, $sCoreHash );
		}
		catch ( \Exception $oE ) {
			$bValid = false;
		}
		return $bValid;
	}
}