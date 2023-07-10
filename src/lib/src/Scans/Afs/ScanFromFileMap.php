<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\ScanControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Common\ScanActionConsumer;
use FernleafSystems\Wordpress\Services\Utilities\Code\AssessPhpFile;

class ScanFromFileMap {

	use ScanControllerConsumer;
	use ModConsumer;
	use ScanActionConsumer;

	public function run() :ResultsSet {
		$opts = $this->opts();

		$action = $this->getScanActionVO();
		$results = $this->getScanController()->getNewResultsSet();

		$isAutoFilter = $opts->isAutoFilterResults();

		if ( is_array( $action->items ) ) {
			foreach ( $action->items as $fullPath ) {

				$fullPath = base64_decode( $fullPath );

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