<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities;

class FileScanner extends Shield\Scans\Base\Files\BaseFileScanner {

	/**
	 * @var Utilities\File\LocateStrInFile
	 */
	private $locator;

	public function __construct() {
		$this->locator = new Utilities\File\LocateStrInFile();
	}

	/**
	 * @param string $fullPath
	 * @return ResultItem|null
	 */
	public function scan( string $fullPath ) {
		$item = null;

		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();

		try {
			$this->locator->setPath( $fullPath );
			{ // Simple Patterns first
				$this->locator->setIsRegEx( false );
				foreach ( $action->patterns_simple as $signature ) {
					$item = $this->scanForSig( $signature );
					if ( !empty( $item ) ) {
						break;
					}
				}
			}

			if ( empty( $item ) ) {
				// RegEx Patterns
				$this->locator->setIsRegEx( true );
				if ( empty( $action->patterns_fullregex ) ) {
					foreach ( $action->patterns_regex as $signature ) {
						$item = $this->scanForSig( $signature );
						if ( !empty( $item ) ) {
							break;
						}
					}
				}
				else { // Full regex patterns
					foreach ( $action->patterns_fullregex as $signature ) {
						$item = $this->scanForSig( $signature );
						if ( !empty( $item ) ) {
							break;
						}
					}
				}
			}
		}
		catch ( \Exception $e ) {
		}

		return $item;
	}

	/**
	 * @param string $signature
	 * @return ResultItem|null
	 */
	private function scanForSig( string $signature ) {
		$resultItem = null;
		$lines = $this->locator->setNeedle( $signature )->run();

		if ( !empty( $lines ) ) {

			$fullPath = $this->locator->getPath();

			if ( $this->canExcludeFile( $fullPath ) ) { // we report false positives: file and lines
				$reporter = ( new Shield\Scans\Mal\Utilities\FalsePositiveReporter() )
					->setMod( $this->getMod() );
				foreach ( $lines as $line ) {
					$reporter->reportLine( $fullPath, $line, true );
				}
				$reporter->reportPath( $fullPath, true );
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
						$lineScores = ( new Shield\Scans\Mal\Utilities\FalsePositiveQuery() )
							->setMod( $this->getMod() )
							->queryFileLines( $fullPath, array_keys( $lines ) );
						$lines = array_filter(
							$lineScores,
							function ( $score ) use ( $action ) {
								return $score < $action->confidence_threshold;
							}
						);

						if ( empty( $lines ) ) {
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
					$resultItem = $this->getResultItemFromLines( array_keys( $lines ), $fullPath, $signature );
				}
			}
		}
		return $resultItem;
	}

	/**
	 * @param string[] $lines
	 * @param string   $fullPath
	 * @param string   $signature
	 * @return ResultItem
	 */
	private function getResultItemFromLines( array $lines, string $fullPath, string $signature ) :ResultItem {
		$item = new ResultItem();
		$item->path_full = wp_normalize_path( $fullPath );
		$item->path_fragment = str_replace( wp_normalize_path( ABSPATH ), '', $item->path_full );
		$item->is_mal = true;
		$item->mal_sig = base64_encode( $signature );
		$item->fp_confidence = 0;
		$item->file_lines = $lines;
		return $item;
	}

	private function canExcludeFile( string $fullPath ) :bool {
		return $this->isValidCoreFile( $fullPath )
			   || $this->isPluginFileValid( $fullPath )
			   || $this->isThemeFileValid( $fullPath );
	}

	private function isPluginFileValid( string $fullPath ) :bool {
		$valid = false;
		try {
			$oPluginFiles = new Utilities\WpOrg\Plugin\Files();
			$plugin = $oPluginFiles->findPluginFromFile( $fullPath );
			if ( !empty( $plugin ) && $plugin->asset_type === 'plugin' ) {
				$valid = $plugin->isWpOrg() ?
					$oPluginFiles->verifyFileContents( $fullPath )
					: $this->verifyPremiumAssetFile( $fullPath, $plugin );
			}
		}
		catch ( \Exception $e ) {
		}

		return $valid;
	}

	private function isThemeFileValid( string $fullPath ) :bool {
		$valid = false;
		try {
			$oThemeFiles = new Utilities\WpOrg\Theme\Files();
			$theme = $oThemeFiles->findThemeFromFile( $fullPath );
			if ( !empty( $theme ) && $theme->asset_type === 'theme' ) {
				$valid = $theme->isWpOrg() ?
					$oThemeFiles->verifyFileContents( $fullPath )
					: $this->verifyPremiumAssetFile( $fullPath, $theme );
			}
		}
		catch ( \Exception $e ) {
		}

		return $valid;
	}

	/**
	 * @param string               $fullPath
	 * @param WpPluginVo|WpThemeVo $oPluginOrTheme
	 * @return bool
	 * @throws \Exception
	 */
	private function verifyPremiumAssetFile( string $fullPath, $oPluginOrTheme ) :bool {
		$valid = false;
		$hashes = ( new Lib\Snapshots\Build\BuildHashesFromApi() )
			->build( $oPluginOrTheme );
		$fragment = str_replace( $oPluginOrTheme->getInstallDir(), '', $fullPath );
		if ( !empty( $hashes ) && !empty( $hashes[ $fragment ] ) ) {
			$valid = ( new Utilities\File\Compare\CompareHash() )
				->isEqualFileMd5( $fullPath, $hashes[ $fragment ] );
		}
		return $valid;
	}

	private function isValidCoreFile( string $fullPath ) :bool {
		$hash = Services::CoreFileHashes()->getFileHash( $fullPath );
		try {
			$valid = !empty( $hash )
					 && ( new Utilities\File\Compare\CompareHash() )->isEqualFileMd5( $fullPath, $hash );
		}
		catch ( \Exception $e ) {
			$valid = false;
		}
		return $valid;
	}
}