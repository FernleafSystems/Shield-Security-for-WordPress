<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Utilities\{
	IsExcludedPhpTranslationFile,
	IsFileContentExcluded
};
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Common\ScanActionConsumer;
use FernleafSystems\Wordpress\Services\Services;

class FileScanner {

	use PluginControllerConsumer;
	use ScanActionConsumer;

	public function scan( string $fullPath ) :?ResultItem {
		$scanCon = self::con()->comps->scans->AFS();
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();

		$item = null;

		$fileExcluded = $this->isFileExcludedFromScans( $fullPath );

		$validFile = false;
		try {
			$validFile =
				$fileExcluded
				|| ( $scanCon->isEnabled() && ( new Scans\WpCoreFile( $fullPath ) )
						->setScanActionVO( $action )
						->isFileValid() )
				|| ( $scanCon->isEnabled() && ( new Scans\WpCoreUnrecognisedFile( $fullPath ) )
						->setScanActionVO( $action )
						->isFileValid() )
				|| ( $scanCon->isScanEnabledWpRoot() && ( new Scans\WpRootUnidentified( $fullPath ) )
						->setScanActionVO( $action )
						->isFileValid() )
				|| ( $scanCon->isScanEnabledPlugins() && ( new Scans\PluginFile( $fullPath ) )
						->setScanActionVO( $action )
						->isFileValid() )
				|| ( $scanCon->isScanEnabledThemes() && ( new Scans\ThemeFile( $fullPath ) )
						->setScanActionVO( $action )
						->isFileValid() )
				|| ( $scanCon->isScanEnabledWpContent() && ( new Scans\WpContentUnidentified( $fullPath ) )
						->setScanActionVO( $action )
						->isFileValid() );
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
			$item->ptg_slug = $e->getScanFileData()[ 'slug' ];
		}
		catch ( Exceptions\PluginFileChecksumFailException $e ) {
			$item = $this->getResultItem( $fullPath );
			$item->is_in_plugin = true;
			$item->is_checksumfail = true;
			$item->ptg_slug = $e->getScanFileData()[ 'slug' ];
		}
		catch ( Exceptions\ThemeFileUnrecognisedException $e ) {
			$item = $this->getResultItem( $fullPath );
			$item->is_in_theme = true;
			$item->is_unrecognised = true;
			$item->ptg_slug = $e->getScanFileData()[ 'slug' ];
		}
		catch ( Exceptions\ThemeFileChecksumFailException $e ) {
			$item = $this->getResultItem( $fullPath );
			$item->is_in_theme = true;
			$item->is_checksumfail = true;
			$item->ptg_slug = $e->getScanFileData()[ 'slug' ];
		}
		catch ( Exceptions\WpRootFileUnidentifiedException $e ) {
			$item = $this->getResultItem( $fullPath );
			$item->is_in_core = true;
			$item->is_in_wproot = true;
			$item->is_unidentified = true;
		}
		catch ( Exceptions\WpContentFileUnidentifiedException $e ) {
			$item = $this->getResultItem( $fullPath );
			$item->is_in_core = true;
			$item->is_in_wpcontent = true;
			$item->is_unidentified = true;
		}
		catch ( \Exception $e ) {
			//Never reached
		}

		if ( !$fileExcluded && $scanCon->isEnabledMalwareScanPHP() && ( empty( $item ) || !$item->is_missing ) ) {
			try {
				( new Scans\MalwareFile( $fullPath ) )
					->setScanActionVO( $action )
					->isFileValid();
			}
			catch ( Exceptions\MalwareFileException $mfe ) {
				$item = $item ?? $this->getResultItem( $fullPath );
				$item->is_mal = true;

				try {
					if ( !isset( $mfe->getScanFileData()[ 'mal_sig' ] ) ) {
						throw new \Exception( 'Cannot proceed without a malware signature' );
					}
					$malRecord = ( new Processing\CreateLocalMalwareRecords() )->run(
						$item->path_fragment,
						$mfe->getScanFileData()[ 'mal_sig' ],
						$validFile
					);
					$item->malware_record_id = $malRecord->id;
					$item->auto_filter = $validFile;
				}
				catch ( \Exception $e ) {
					/** We can't proceed without a linked local Malware Record */
					$item = null;
					error_log( $e->getMessage() );
				}
			}
			catch ( \InvalidArgumentException $e ) {
			}
		}

		if ( !empty( $item ) && Services::WpFs()->isAccessibleFile( $fullPath ) ) {
			$item->checksum_sha256 = \hash_file( 'sha256', $fullPath );
		}

		// If there's no result item, and the file is marked as 'valid', we mark it for optimisation in future scans.
		if ( empty( $item ) && $validFile ) {
			$validFiles = \is_array( $action->valid_files ) ? $action->valid_files : [];
			$validFiles[] = $fullPath;
			$action->valid_files = $validFiles;
		}

		return $item;
	}

	private function getResultItem( string $fullPath ) :ResultItem {
		/** @var ResultItem $item */
		$item = self::con()->comps->scans->AFS()->getNewResultItem();
		$item->path_full = wp_normalize_path( $fullPath );
		$item->path_fragment = \str_replace( wp_normalize_path( ABSPATH ), '', $item->path_full );
		return $item;
	}

	private function isFileExcludedFromScans( string $fullPath ) :bool {
		return ( new IsFileContentExcluded() )->check( $fullPath ) || ( new IsExcludedPhpTranslationFile() )->check( $fullPath );
	}
}