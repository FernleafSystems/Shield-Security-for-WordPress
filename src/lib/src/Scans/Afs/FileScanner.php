<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\Afs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib;

class FileScanner extends Shield\Scans\Base\Files\BaseFileScanner {

	/**
	 * @return ResultItem|null
	 */
	public function scan( string $fullPath ) {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();
		$scans = $action->scans;

		$item = null;

		$validFile = false;
		try {
			$validFile =
				( in_array( Afs::SCAN_SLUG_WCF, $scans ) && ( new Scans\WpCoreFile( $fullPath ) )
						->setMod( $this->getMod() )
						->setScanActionVO( $action )
						->scan() ) ||
				( in_array( Afs::SCAN_SLUG_UFC, $scans ) && ( new Scans\WpCoreUnrecognisedFile( $fullPath ) )
						->setMod( $this->getMod() )
						->setScanActionVO( $action )
						->scan() ) ||
				( in_array( Afs::SCAN_SLUG_PTG, $scans ) && ( new Scans\PluginFile( $fullPath ) )
						->setMod( $this->getMod() )
						->setScanActionVO( $action )
						->scan() ) ||
				( in_array( Afs::SCAN_SLUG_PTG, $scans ) && ( new Scans\ThemeFile( $fullPath ) )
						->setMod( $this->getMod() )
						->setScanActionVO( $action )
						->scan() );
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

		try {
			if ( in_array( Afs::SCAN_SLUG, $scans ) && ( empty( $item ) || !$item->is_missing ) ) {
				( new Scans\MalwareFile( $fullPath ) )
					->setMod( $this->getMod() )
					->setScanActionVO( $action )
					->scan();
			}
		}
		catch ( Exceptions\MalwareFileException $mfe ) {
			if ( empty( $item ) ) {
				$item = $this->getResultItem( $fullPath );
			}
			$item->is_mal = true;

			foreach ( $mfe->getScanFileData() as $malMetaKey => $malMetaValue ) {
				$item->{$malMetaKey} = $malMetaValue;
			}
			if ( $validFile ) {
				$item->mal_fp_confidence = 100;
			}

			// Updates the FP scores stored within mal_meta
			( new Shield\Scans\Afs\Processing\MalwareFalsePositive() )
				->setMod( $this->getMod() )
				->setScanActionVO( $this->getScanActionVO() )
				->run( $item );

			if ( $item->mal_fp_confidence > $action->confidence_threshold ) {
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