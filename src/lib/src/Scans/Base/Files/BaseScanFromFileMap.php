<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Files;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\AssessPhpFile;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class BaseScanFromFileMap
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Files
 */
abstract class BaseScanFromFileMap {

	use ModConsumer;
	use Scans\Common\ScanActionConsumer;

	/**
	 * @return Scans\Base\ResultsSet
	 */
	public function run() {
		$action = $this->getScanActionVO();
		$results = $action->getNewResultsSet();

		if ( is_array( $action->items ) ) {
			foreach ( $action->items as $key => $fullPath ) {

				if ( !$this->isEmptyOfCode( $fullPath ) ) {
					$item = $this->getFileScanner()->scan( $fullPath );
					// We can exclude files that are empty of relevant code
					if ( $item instanceof Scans\Base\ResultItem ) {
						$results->addItem( $item );
					}
				}
				else {
					error_log( 'empty' );
					error_log( $fullPath );
				}
			}
		}

		return $results;
	}

	/**
	 * @return BaseFileScanner
	 */
	abstract protected function getFileScanner();

	protected function isEmptyOfCode( string $path ) :bool {
		try {
			if ( strpos( $path, wp_normalize_path( ABSPATH ) ) === false ) {
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