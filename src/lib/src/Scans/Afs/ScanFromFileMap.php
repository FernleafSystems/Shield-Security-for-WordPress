<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Common\ScanActionConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Code\AssessPhpFile;

class ScanFromFileMap {

	use PluginControllerConsumer;
	use ScanActionConsumer;

	public function run() :ResultsSet {
		$action = $this->getScanActionVO();
		$results = self::con()
			->comps
			->scans
			->AFS()
			->getNewResultsSet();

		$isAutoFilter = self::con()->comps->opts_lookup->isScanAutoFilterResults();

		if ( \is_array( $action->items ) ) {
			foreach ( $action->items as $fullPath ) {
				$fullPath = \base64_decode( $fullPath );

				$canScan = !empty( $fullPath )
						   && $this->isAllowableFileSize( $fullPath )
						   && ( !$isAutoFilter || !$this->isEmptyOfCode( $fullPath ) );

				// We can exclude files that are empty of relevant code
				if ( $canScan ) {
					$item = ( new FileScanner() )
						->setScanActionVO( $action )
						->scan( $fullPath );
					if ( !empty( $item ) ) {
						$results->addItem( $item );
					}
				}
			}
		}

		return $results;
	}

	/**
	 * Allowable size if the file doesn't exist at all (this will be picked up in the actual scan)
	 * or, the file exists & it's below the max
	 */
	protected function isAllowableFileSize( string $path ) :bool {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();
		return !Services::WpFs()->isAccessibleFile( $path )
			   || Services::WpFs()->getFileSize( $path ) < $action->max_file_size;
	}

	protected function isEmptyOfCode( string $path ) :bool {
		try {
			if ( \strpos( $path, wp_normalize_path( ABSPATH ) ) === false ) {
				$path = path_join( ABSPATH, $path );
			}
			$isEmpty = ( new AssessPhpFile() )->isEmptyOfCode( $path );
		}
		catch ( \Exception $e ) {
			$isEmpty = false;
		}
		return $isEmpty;
	}
}