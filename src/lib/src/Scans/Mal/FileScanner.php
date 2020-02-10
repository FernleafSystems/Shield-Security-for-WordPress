<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib;
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
		if ( !empty( $aLines ) ) {

			if ( $this->canExcludeFile( $sFullPath ) ) { // we report false positives: file and lines
				$oReporter = ( new Shield\Scans\Mal\Utilities\FalsePositiveReporter() )
					->setMod( $this->getMod() );
				foreach ( $aLines as $nLine => $sLine ) {
					$oReporter->reportLine( $sFullPath, $sLine, true );
				}
				$oReporter->reportPath( $sFullPath, true );
			}
			else {
				$oAction = $this->getScanActionVO();

				if ( $oAction->confidence_threshold > 0 ) {
					$bReportItem = false;
					// 1. First check whether the FP of the whole file means we can filter it
					$nFalsePositiveConfidence = ( new Shield\Scans\Mal\Utilities\FalsePositiveQuery() )
						->setMod( $this->getMod() )
						->queryPath( $sFullPath );
					if ( $nFalsePositiveConfidence < $oAction->confidence_threshold ) {
						// 2. Check each line and filter out fp confident lines
						$aLineScores = ( new Shield\Scans\Mal\Utilities\FalsePositiveQuery() )
							->setMod( $this->getMod() )
							->queryFileLines( $sFullPath, array_keys( $aLines ) );
						$aLines = array_filter(
							$aLineScores,
							function ( $nScore ) use ( $oAction ) {
								return $nScore < $oAction->confidence_threshold;
							}
						);

						if ( empty( $aLines ) ) {
							// Now send False Positive report for entire file based on all file lines being FPs.
							( new Shield\Scans\Mal\Utilities\FalsePositiveReporter() )
								->setMod( $this->getMod() )
								->reportPath( $sFullPath, true );
						}
						else {
							$bReportItem = true;
						}
					}
				}
				else {
					$bReportItem = true;
				}

				if ( $bReportItem ) {
					$oResultItem = $this->getResultItemFromLines( array_keys( $aLines ), $sFullPath, $sSig );
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
				if ( $oPlugin->isWpOrg() ) {
					$bIsValidFile = ( new Utilities\WpOrg\Plugin\Files() )->verifyFileContents( $sFullPath );
				}
				else {
					$aHashes = ( new Lib\Snapshots\Build\BuildHashesFromApi() )
						->build( $oPlugin );
					$sFragment = str_replace( $oPlugin->getInstallDir(), '', $sFullPath );
					if ( !empty( $aHashes ) && !empty( $aHashes[ $sFragment ] ) ) {
						$bIsValidFile = ( new Utilities\File\Compare\CompareHash() )
							->isEqualFileMd5( $sFullPath, $aHashes[ $sFragment ] );
					}
				}
			}
		}
		catch ( \Exception $oE ) {
			$bIsValidFile = false;
		}
		return $bIsValidFile;
	}

	/**
	 * @param string $sFullPath - normalized
	 * @return bool
	 */
	private function isThemeFileValid( $sFullPath ) {
		try {
			$bIsValidFile = ( new Utilities\WpOrg\Theme\Files() )->verifyFileContents( $sFullPath );
		}
		catch ( \Exception $oE ) {
			$bIsValidFile = false;
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