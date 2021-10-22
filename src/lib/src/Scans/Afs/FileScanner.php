<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib;

class FileScanner extends Shield\Scans\Base\Files\BaseFileScanner {

	/**
	 * @return ResultItem|null
	 */
	public function scan( string $fullPath ) {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();

		$item = null;

		$validFile = false;
		try {
			$validFile = ( new Scans\WpCoreFile( $fullPath ) )
							 ->setScanActionVO( $action )
							 ->scan()
						 || ( new Scans\WpCoreUnrecognisedFile( $fullPath ) )
							 ->setScanActionVO( $action )
							 ->scan()
						 || ( new Scans\PluginFile( $fullPath ) )
							 ->setMod( $this->getMod() )
							 ->setScanActionVO( $action )
							 ->scan()
						 || ( new Scans\ThemeFile( $fullPath ) )
							 ->setMod( $this->getMod() )
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

		/**
		 * TODO: flag to bypass malware scan
		 */
		try {
			if ( empty( $item ) || !$item->is_missing ) {
				( new Scans\MalwareFile( $fullPath ) )
					->setMod( $this->getMod() )
					->setScanActionVO( $action )
					->scan();
			}
		}
		catch ( Exceptions\MalwareFileException $mfe ) {
			$item = $this->getResultItem( $fullPath );
			$item->is_mal = true;

			$malMeta = $mfe->getScanFileData();
			if ( $validFile ) {
				$malMeta[ 'fp_confidence' ] = 100;
			}
			$item->mal_meta = $malMeta;

			// Updates the FP scores stored within mal_meta
			( new Shield\Scans\Afs\Processing\MalwareFalsePositive() )
				->setMod( $this->getMod() )
				->setScanActionVO( $this->getScanActionVO() )
				->run( $item );

			if ( $item->mal_meta[ 'fp_confidence' ] > $action->confidence_threshold ) {
				$item->auto_filter = true;
			}
		}
		catch ( \InvalidArgumentException $e ) {
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