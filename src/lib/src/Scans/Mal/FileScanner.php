<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Core\VOs\WpPluginVo;
use FernleafSystems\Wordpress\Services\Core\VOs\WpThemeVo;
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
		$oItem = null;

		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();

		try {
			$oLocator = ( new Utilities\File\LocateStrInFile() )->setPath( $sFullPath );

			{ // Simple Patterns first
				$oLocator->setIsRegEx( false );
				foreach ( $oAction->patterns_simple as $sSig ) {
					$oItem = $this->scanForSig( $oLocator, $sSig );
					if ( $oItem instanceof ResultItem ) {
						return $oItem;
					}
				}
			}

			{ // RegEx Patterns
				$oLocator->setIsRegEx( true );
				foreach ( $oAction->patterns_regex as $sSig ) {
					$oItem = $this->scanForSig( $oLocator, $sSig );
					if ( $oItem instanceof ResultItem ) {
						return $oItem;
					}
				}
			}
		}
		catch ( \Exception $oE ) {
		}

		return $oItem;
	}

	/**
	 * @param Utilities\File\LocateStrInFile $oLocator
	 * @param string                         $sSig
	 * @return ResultItem|null
	 */
	private function scanForSig( $oLocator, $sSig ) {
		$oResultItem = null;

		$aLines = $oLocator->setNeedle( $sSig )
						   ->run();
		$sFullPath = $oLocator->getPath();
		if ( !empty( $aLines ) && !$this->canExcludeFile( $sFullPath ) ) {

			$oMaybeItem = $this->getResultItemFromLines( $aLines, $sFullPath, $sSig );
			$oAction = $this->getScanActionVO();
			// Zero indicates not using intelligence network
			if ( $oAction->confidence_threshold > 0 ) {
				$oMaybeItem->fp_confidence = $this->getFalsePositiveConfidence( $sFullPath );
			}

			if ( $oAction->confidence_threshold == 0 || $oMaybeItem->fp_confidence < $oAction->confidence_threshold ) {
				$oResultItem = $oMaybeItem;
			}
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
		$oResultItem->fp_confidence = 0;
		$oResultItem->file_lines = array_map(
			function ( $nLineNumber ) {
				return $nLineNumber + 1;
			},
			$aLines // because lines start at ZERO
		);
		return $oResultItem;
	}

	/**
	 * @param string $sFullPath - normalized
	 * @return bool
	 */
	private function canExcludeFile( $sFullPath ) {
		$bExclude = $this->isValidCoreFile( $sFullPath )
					|| $this->isPluginFileValid( $sFullPath ) || $this->isThemeFileValid( $sFullPath );

		if ( $bExclude ) {
			( new Shield\Scans\Mal\Utilities\FalsePositiveReporter() )
				->setMod( $this->getMod() )
				->report( $sFullPath, 'sha1', true );
		}
		return $bExclude;
	}

	/**
	 * @param string $sFilePath
	 * @return int
	 */
	private function getFalsePositiveConfidence( $sFilePath ) {
		/** @var ScanActionVO $oScanVO */
		$oScanVO = $this->getScanActionVO();

		$nConfidence = 0;
		$sFilePart = basename( $sFilePath );
		if ( isset( $oScanVO->whitelist[ $sFilePart ] ) ) {
			try {
				$oHasher = new Utilities\File\Compare\CompareHash();
				foreach ( $oScanVO->whitelist[ $sFilePart ] as $sWlHash => $nHashConfidence ) {
					if ( $oHasher->isEqualFileSha1( $sFilePath, $sWlHash ) ) {
						$nConfidence = $nHashConfidence;
						break;
					}
				}
			}
			catch ( \InvalidArgumentException $oE ) {
			}
		}
		return (int)$nConfidence;
	}

	/**
	 * @param string $sFullPath - normalized
	 * @return bool
	 */
	private function isPluginFileValid( $sFullPath ) {
		$bCanExclude = false;

		if ( strpos( $sFullPath, wp_normalize_path( WP_PLUGIN_DIR ) ) === 0 ) {

			$oFiles = new Utilities\WpOrg\Plugin\Files();
			$oThePlugin = $oFiles->findPluginFromFile( $sFullPath );
			if ( $oThePlugin instanceof WpPluginVo ) {

				$oVersion = ( new Utilities\WpOrg\Plugin\Versions() )
					->setWorkingSlug( $oThePlugin->slug )
					->setWorkingVersion( $oThePlugin->Version );

				// Only try to download load a file if the plugin actually uses SVN Tags for this version.
				if ( $oVersion->exists( $oThePlugin->Version, true ) ) {
					try {
						$sTmpFile = $oFiles
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
	 * @param string $sFullPath - normalized
	 * @return bool
	 */
	private function isThemeFileValid( $sFullPath ) {
		$bCanExclude = false;

		if ( strpos( $sFullPath, wp_normalize_path( get_theme_root() ) ) === 0 ) {
			$oFiles = new Utilities\WpOrg\Theme\Files();
			$oTheTheme = $oFiles->findThemeFromFile( $sFullPath );
			if ( $oTheTheme instanceof WpThemeVo ) {
				$oVersion = ( new Utilities\WpOrg\Theme\Versions() )
					->setWorkingSlug( $oTheTheme->stylesheet )
					->setWorkingVersion( $oTheTheme->wp_theme->get( 'Version' ) );

				// Only try to download load a file if the theme actually uses SVN Tags for this version.
				if ( $oVersion->exists( $oTheTheme->wp_theme->get( 'Version' ), true ) ) {
					try {
						$bCanExclude = $oFiles
							->setWorkingSlug( $oTheTheme->stylesheet )
							->setWorkingVersion( $oTheTheme->wp_theme->get( 'Version' ) )
							->verifyFileContents( $sFullPath );
					}
					catch ( \Exception $oE ) {
//						error_log( $oE->getMessage() );
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