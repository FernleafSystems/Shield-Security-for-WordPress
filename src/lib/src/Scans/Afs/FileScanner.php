<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\Afs as AfsCon;

class FileScanner {

	use Shield\Modules\HackGuard\Scan\Controller\ScanControllerConsumer;
	use Shield\Modules\ModConsumer;
	use Shield\Scans\Common\ScanActionConsumer;

	public function scan( string $fullPath ) :?ResultItem {
		/** @var AfsCon $scanCon */
		$scanCon = $this->getScanController();
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();

		$item = null;

		$validFile = false;
		try {
			$validFile =
				( $scanCon->isEnabled() && ( new Scans\WpCoreFile( $fullPath ) )
						->setScanActionVO( $action )
						->isFileValid() ) ||
				( $scanCon->isEnabled() && ( new Scans\WpCoreUnrecognisedFile( $fullPath ) )
						->setScanActionVO( $action )
						->isFileValid() ) ||
				( $scanCon->isScanEnabledWpRoot() && ( new Scans\WpRootUnidentified( $fullPath ) )
						->setScanActionVO( $action )
						->isFileValid() ) ||
				( $scanCon->isScanEnabledPlugins() && ( new Scans\PluginFile( $fullPath ) )
						->setScanActionVO( $action )
						->isFileValid() ) ||
				( $scanCon->isScanEnabledThemes() && ( new Scans\ThemeFile( $fullPath ) )
						->setScanActionVO( $action )
						->isFileValid() );
				( $scanCon->isScanEnabledWpContent() && ( new Scans\WpContentUnidentified( $fullPath ) )
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

		if ( $scanCon->isEnabledMalwareScanPHP() && ( empty( $item ) || !$item->is_missing ) ) {
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

		// If there's no result item, and the file is marked as 'valid', we mark it for optimisation in future scans.
		if ( empty( $item ) && $validFile ) {
			$validFiles = is_array( $action->valid_files ) ? $action->valid_files : [];
			$validFiles[] = $fullPath;
			$action->valid_files = $validFiles;
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
}