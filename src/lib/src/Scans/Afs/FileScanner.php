<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\LocateStrInFile;

class FileScanner extends Shield\Scans\Base\Files\BaseFileScanner {

	/**
	 * @return ResultItem|null
	 */
	public function scan( string $fullPath ) {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();

		// 1. Is WP Core
		try {
			$valid = ( new Scans\WpCoreFile( $fullPath ) )
						 ->setScanActionVO( $action )
						 ->scan()
					 || ( new Scans\WpCoreUnrecognisedFile( $fullPath ) )
						 ->setScanActionVO( $action )
						 ->scan()
					 || ( new Scans\PluginFile( $fullPath ) )
						 ->setScanActionVO( $action )
						 ->scan()
					 || ( new Scans\ThemeFile( $fullPath ) )
						 ->setScanActionVO( $action )
						 ->scan();
		}
		catch ( Exceptions\WpCoreFileMissingException $me ) {
			$item = $this->getResultItem( $fullPath );
			$item->is_in_core = true;
			$item->is_missing = true;
		}
		catch ( Exceptions\WpCoreFileChecksumFailException $cfe ) {
			$item = $this->getResultItem( $fullPath );
			$item->is_in_core = true;
			$item->is_checksumfail = true;
		}
		catch ( Exceptions\WpCoreFileUnrecognisedException $ufe ) {
			$item = $this->getResultItem( $fullPath );
			$item->is_in_core = true;
			$item->is_unrecognised = true;
		}
		catch ( Exceptions\PluginFileUnrecognisedException $e ) {
			$item = $this->getResultItem( $fullPath );
			$item->is_in_plugin = true;
			$item->is_unrecognised = true;
		}
		catch ( Exceptions\PluginFileChecksumFailException $e ) {
			$item = $this->getResultItem( $fullPath );
			$item->is_in_plugin = true;
			$item->is_checksumfail = true;
		}
		catch ( Exceptions\ThemeFileUnrecognisedException $e ) {
			$item = $this->getResultItem( $fullPath );
			$item->is_in_theme = true;
			$item->is_unrecognised = true;
		}
		catch ( Exceptions\ThemeFileChecksumFailException $e ) {
			$item = $this->getResultItem( $fullPath );
			$item->is_in_theme = true;
			$item->is_checksumfail = true;
		}

		// 5. Is Malware.

		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();

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

	private function getResultItem( string $fullPath ) :ResultItem {
		/** @var ResultItem $item */
		$item = $this->getScanController()->getNewResultItem();
		$item->path_full = wp_normalize_path( $fullPath );
		$item->path_fragment = str_replace( wp_normalize_path( ABSPATH ), '', $item->path_full );
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