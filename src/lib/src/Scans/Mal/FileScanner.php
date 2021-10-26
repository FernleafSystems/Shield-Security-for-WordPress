<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\LocateStrInFile;

class FileScanner extends Shield\Scans\Base\Files\BaseFileScanner {

	/**
	 * @var LocateStrInFile
	 */
	private $locator;

	public function __construct() {
		$this->locator = new LocateStrInFile();
	}

	/**
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
	 * @return ResultItem|null
	 */
	private function scanForSig( string $signature ) {
		$item = null;
		$lines = $this->locator->setNeedle( $signature )->run();

		if ( !empty( $lines ) ) {
			/** @var ScanActionVO $action */
			$action = $this->getScanActionVO();

			$fullPath = $this->locator->getPath();
			$item = $this->getResultItemFromLines( array_keys( $lines ), $fullPath, $signature );

			// we report false positives: file and lines
			if ( $this->canExcludeFile( $fullPath ) ) {
				$reporter = ( new Utilities\FalsePositiveReporter() )
					->setMod( $this->getMod() );
				foreach ( $lines as $line ) {
					$reporter->reportLine( $fullPath, $line, true );
				}
				$reporter->reportPath( $fullPath, true );

				$item->auto_filter = true;
				$item->fp_confidence = 100;
				$item->file_lines = array_fill_keys( array_keys( $lines ), 100 );
			}
			elseif ( $action->confidence_threshold > 0 ) {

				// 1. First check whether the FP of the whole file means we can filter it
				$fpConfidence = ( new Utilities\FalsePositiveQuery() )
					->setMod( $this->getMod() )
					->queryPath( $fullPath );

				// 2. Check each line and filter out fp confident lines
				if ( $fpConfidence < $action->confidence_threshold ) {
					$item->file_lines = ( new Utilities\FalsePositiveQuery() )
						->setMod( $this->getMod() )
						->queryFileLines( $fullPath, array_keys( $lines ) );

					$filteredLines = array_filter(
						$item->file_lines,
						function ( $score ) use ( $action ) {
							return $score < $action->confidence_threshold;
						}
					);

					if ( empty( $filteredLines ) ) {
						// Now send False Positive report for entire file based on all file lines being FPs.
						( new Utilities\FalsePositiveReporter() )
							->setMod( $this->getMod() )
							->reportPath( $fullPath, true );
					}
				}
			}
		}
		return $item;
	}

	/**
	 * @param string[] $lines
	 * @param string   $fullPath
	 * @param string   $signature
	 * @return ResultItem
	 */
	private function getResultItemFromLines( array $lines, string $fullPath, string $signature ) :ResultItem {
		/** @var ResultItem $item */
		$item = $this->getScanController()->getNewResultItem();
		$item->path_full = wp_normalize_path( $fullPath );
		$item->path_fragment = str_replace( wp_normalize_path( ABSPATH ), '', $item->path_full );
		$item->mal_sig = base64_encode( $signature );
		$item->fp_confidence = 0;
		$item->file_lines = $lines;
		return $item;
	}

	private function canExcludeFile( string $fullPath ) :bool {
		try {
			$validHash = Services::CoreFileHashes()->isCoreFileHashValid( $fullPath )
						 || ( new Lib\Hashes\Query() )
							 ->setMod( $this->getMod() )
							 ->verifyHash( $fullPath );
		}
		catch ( \Exception $e ) {
			$validHash = false;
		}
		return $validHash;
	}
}