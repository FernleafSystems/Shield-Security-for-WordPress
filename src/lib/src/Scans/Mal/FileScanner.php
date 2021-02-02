<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib;
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
	 * @param string $fullPath
	 * @return ResultItem|null
	 */
	public function scan( $fullPath ) {
		$oItem = null;

		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();

		try {
			$locator = ( new Utilities\File\LocateStrInFile() )->setPath( $fullPath );

			{ // Simple Patterns first
				$locator->setIsRegEx( false );
				foreach ( $action->patterns_simple as $sSig ) {
					$oItem = $this->scanForSig( $locator, $sSig );
					if ( $oItem instanceof ResultItem ) {
						return $oItem;
					}
				}
			}

			{ // RegEx Patterns
				$locator->setIsRegEx( true );
				foreach ( $action->patterns_regex as $sSig ) {
					$oItem = $this->scanForSig( $locator, $sSig );
					if ( $oItem instanceof ResultItem ) {
						return $oItem;
					}
				}
			}
		}
		catch ( \Exception $e ) {
		}

		return $oItem;
	}

	/**
	 * @param Utilities\File\LocateStrInFile $oLocator
	 * @param string                         $signature
	 * @return ResultItem|null
	 */
	private function scanForSig( $oLocator, $signature ) {
		$oResultItem = null;

		$aLines = $oLocator->setNeedle( $signature )
						   ->run();

		$fullPath = $oLocator->getPath();
		if ( !empty( $aLines ) ) {

			if ( $this->canExcludeFile( $fullPath ) ) { // we report false positives: file and lines
				$oReporter = ( new Shield\Scans\Mal\Utilities\FalsePositiveReporter() )
					->setMod( $this->getMod() );
				foreach ( $aLines as $nLine => $sLine ) {
					$oReporter->reportLine( $fullPath, $sLine, true );
				}
				$oReporter->reportPath( $fullPath, true );
			}
			else {
				/** @var ScanActionVO $action */
				$action = $this->getScanActionVO();

				if ( $action->confidence_threshold > 0 ) {
					$reportItem = false;
					// 1. First check whether the FP of the whole file means we can filter it
					$nFalsePositiveConfidence = ( new Shield\Scans\Mal\Utilities\FalsePositiveQuery() )
						->setMod( $this->getMod() )
						->queryPath( $fullPath );
					if ( $nFalsePositiveConfidence < $action->confidence_threshold ) {
						// 2. Check each line and filter out fp confident lines
						$aLineScores = ( new Shield\Scans\Mal\Utilities\FalsePositiveQuery() )
							->setMod( $this->getMod() )
							->queryFileLines( $fullPath, array_keys( $aLines ) );
						$aLines = array_filter(
							$aLineScores,
							function ( $nScore ) use ( $action ) {
								return $nScore < $action->confidence_threshold;
							}
						);

						if ( empty( $aLines ) ) {
							// Now send False Positive report for entire file based on all file lines being FPs.
							( new Shield\Scans\Mal\Utilities\FalsePositiveReporter() )
								->setMod( $this->getMod() )
								->reportPath( $fullPath, true );
						}
						else {
							$reportItem = true;
						}
					}
				}
				else {
					$reportItem = true;
				}

				if ( $reportItem ) {
					$oResultItem = $this->getResultItemFromLines( array_keys( $aLines ), $fullPath, $signature );
				}
			}
		}
		return $oResultItem;
	}

	/**
	 * @param string[] $aLines
	 * @param string   $sFullPath
	 * @param string   $sSig
	 * @return ResultItem
	 */
	private function getResultItemFromLines( $aLines, $sFullPath, $sSig ) {
		$oResultItem = new ResultItem();
		$oResultItem->path_full = wp_normalize_path( $sFullPath );
		$oResultItem->path_fragment = str_replace( wp_normalize_path( ABSPATH ), '', $oResultItem->path_full );
		$oResultItem->is_mal = true;
		$oResultItem->mal_sig = base64_encode( $sSig );
		$oResultItem->fp_confidence = 0;
		$oResultItem->file_lines = $aLines;
		return $oResultItem;
	}

	/**
	 * @param string $sFullPath - normalized
	 * @return bool
	 */
	private function canExcludeFile( $sFullPath ) {
		return $this->isValidCoreFile( $sFullPath )
			   || $this->isPluginFileValid( $sFullPath ) || $this->isThemeFileValid( $sFullPath );
	}

	/**
	 * @param string $sFullPath - normalized
	 * @return bool
	 */
	private function isPluginFileValid( $sFullPath ) {
		$bIsValidFile = false;
		try {
			$oPluginFiles = new Utilities\WpOrg\Plugin\Files();
			$oPlugin = $oPluginFiles->findPluginFromFile( $sFullPath );
			if ( $oPlugin instanceof WpPluginVo ) {
				$bIsValidFile = $oPlugin->isWpOrg() ?
					$oPluginFiles->verifyFileContents( $sFullPath )
					: $this->verifyPremiumAssetFile( $sFullPath, $oPlugin );
			}
		}
		catch ( \Exception $oE ) {
		}

		return $bIsValidFile;
	}

	/**
	 * @param string $sFullPath - normalized
	 * @return bool
	 */
	private function isThemeFileValid( $sFullPath ) {
		$bIsValidFile = false;
		try {
			$oThemeFiles = new Utilities\WpOrg\Theme\Files();
			$oTheme = $oThemeFiles->findThemeFromFile( $sFullPath );
			if ( $oTheme instanceof WpThemeVo ) {
				$bIsValidFile = $oTheme->isWpOrg() ?
					$oThemeFiles->verifyFileContents( $sFullPath )
					: $this->verifyPremiumAssetFile( $sFullPath, $oTheme );
			}
		}
		catch ( \Exception $oE ) {
		}

		return $bIsValidFile;
	}

	/**
	 * @param string               $sFullPath
	 * @param WpPluginVo|WpThemeVo $oPluginOrTheme
	 * @return bool
	 * @throws \Exception
	 */
	private function verifyPremiumAssetFile( $sFullPath, $oPluginOrTheme ) {
		$bIsValidFile = false;
		$aHashes = ( new Lib\Snapshots\Build\BuildHashesFromApi() )
			->build( $oPluginOrTheme );
		$sFragment = str_replace( $oPluginOrTheme->getInstallDir(), '', $sFullPath );
		if ( !empty( $aHashes ) && !empty( $aHashes[ $sFragment ] ) ) {
			$bIsValidFile = ( new Utilities\File\Compare\CompareHash() )
				->isEqualFileMd5( $sFullPath, $aHashes[ $sFragment ] );
		}
		return $bIsValidFile;
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