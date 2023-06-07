<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs;

use FernleafSystems\Wordpress\Plugin\Shield;

class Scan extends Shield\Scans\Base\BaseScan {

	/**
	 * @throws \Exception
	 */
	protected function preScan() {
		parent::preScan();

		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();

		if ( $this->opts()->isOpt( 'optimise_scan_speed', 'Y' ) ) {
			( new Processing\FileScanOptimiser() )->filterFilesFromAction( $action );
		}

		$patterns = ( new Utilities\MalwareScanPatterns() )->retrieve();
		$action->patterns_raw = $patterns[ 'raw' ];
		$action->patterns_iraw = $patterns[ 'iraw' ];
		$action->patterns_regex = $patterns[ 're' ];
		$action->patterns_functions = $patterns[ 'functions' ];
		$action->patterns_keywords = $patterns[ 'keywords' ];
	}

	protected function scanSlice() {
		$action = $this->getScanActionVO();

		$action->results = array_map(
			function ( $item ) {
				return $item->getRawData();
			},
			// run the scan and get results:
			( new ScanFromFileMap() )
				->setScanController( $this->getScanController() )
				->setScanActionVO( $action )
				->run()
				->getAllItems()
		);
	}

	protected function postScan() {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();
		if ( $this->opts()->isOpt( 'optimise_scan_speed', 'Y' ) && \is_array( $action->valid_files ) ) {
			( new Processing\FileScanOptimiser() )->addFiles( $action->valid_files );
		}
	}
}