<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

use FernleafSystems\Wordpress\Plugin\Shield;
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
					$oReporter->reportSignature( $sLine, true, $sFullPath );
				}
				$oReporter->reportPath( $sFullPath, 'sha1', true );
			}
			else {
				// First filter out lines based on confidence threshold.
				$aLines = $this->filterLinesBasedOnConfidence( $aLines );

				if ( !empty( $aLines ) ) {
					$oAction = $this->getScanActionVO();
					$nFalsePositiveConfidence = $this->getFalsePositiveConfidenceForFile( $sFullPath );
					if ( $oAction->confidence_threshold == 0 || $nFalsePositiveConfidence < $oAction->confidence_threshold ) {
						$oResultItem = $this->getResultItemFromLines( array_keys( $aLines ), $sFullPath, $sSig );
						$oResultItem->fp_confidence = $nFalsePositiveConfidence;
					}
				}
			}
		}
		return $oResultItem;
	}

	/**
	 * @param string[] $aLines
	 * @return string[]
	 */
	private function filterLinesBasedOnConfidence( $aLines ) {
		$oAction = $this->getScanActionVO();
		if ( $oAction->confidence_threshold > 0 ) {
			$aLines = array_filter( $aLines,
				function ( $sLine ) use ( $oAction ) {
					/** @var string $sLine */
					return $this->getFalsePositiveConfidenceForLine( $sLine ) < $oAction->confidence_threshold;
				}
			);
		}
		return $aLines;
	}

	/**
	 * @param string $sLine
	 * @return int
	 */
	private function getFalsePositiveConfidenceForLine( $sLine ) {
		/** @var ScanActionVO $oScanVO */
		$oScanVO = $this->getScanActionVO();

		$nConfidence = 0;
		if ( $oScanVO->confidence_threshold > 0 ) {
			$sEncodedLine = base64_encode( trim( $sLine ) );
			if ( isset( $oScanVO->fp_signatures[ $sEncodedLine ] ) ) {
				$nConfidence = $oScanVO->fp_signatures[ $sEncodedLine ];
			}
		}
		return (int)$nConfidence;
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
		return $this->isValidCoreFile( $sFullPath )
			   || $this->isPluginFileValid( $sFullPath ) || $this->isThemeFileValid( $sFullPath );
	}

	/**
	 * @param string $sFilePath
	 * @return int
	 */
	private function getFalsePositiveConfidenceForFile( $sFilePath ) {
		/** @var ScanActionVO $oScanVO */
		$oScanVO = $this->getScanActionVO();

		$nConfidence = 0;
		$sFilePart = basename( $sFilePath );
		if ( $oScanVO->confidence_threshold > 0 && isset( $oScanVO->whitelist[ $sFilePart ] ) ) {
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
		try {
			$bIsValidFile = ( new Utilities\WpOrg\Plugin\Files() )->verifyFileContents( $sFullPath );
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