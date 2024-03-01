<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Common\ScanActionConsumer;
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

				// We can exclude files that are empty of relevant code
				if ( !$isAutoFilter || !$this->isEmptyOfCode( $fullPath ) ) {

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