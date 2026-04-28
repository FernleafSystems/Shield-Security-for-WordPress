<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs;

class Scan extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseScan {

	/**
	 * @throws \Exception
	 */
	protected function preScan() {
		parent::preScan();

		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();

		if ( self::con()->opts->optIs( 'optimise_scan_speed', 'Y' ) ) {
			$optimiser = new Processing\FileScanOptimiser();
			if ( !empty( $action->items ) ) {
				$action->items = \array_values( \array_filter(
					$action->items,
					function ( $item ) use ( $action, $optimiser ) {
						$path = \base64_decode( (string)$item, true );
						return !\is_string( $path ) || !$optimiser->canSkipKnownValidFile( $path, $action );
					}
				) );
			}
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
		$action->results = \array_map(
			fn( $item ) => $item->getRawData(),
			// run the scan and get results:
			( new ScanFromFileMap() )
				->setScanActionVO( $action )
				->run()
				->getAllItems()
		);
	}
}
