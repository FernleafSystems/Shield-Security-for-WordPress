<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs;

class Scan extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseScan {

	private const PRESCAN_HEARTBEAT_ITEM_INTERVAL = 1000;

	/**
	 * @throws \Exception
	 */
	protected function preScan() {
		parent::preScan();

		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();

		if ( !empty( $action->items ) ) {
			$this->filterKnownValidItems( $action );
		}

		$patterns = ( new Utilities\MalwareScanPatterns() )->retrieve();
		$action->patterns_raw = $patterns[ 'raw' ];
		$action->patterns_iraw = $patterns[ 'iraw' ];
		$action->patterns_regex = $patterns[ 're' ];
		$action->patterns_functions = $patterns[ 'functions' ];
		$action->patterns_keywords = $patterns[ 'keywords' ];
	}

	protected function filterKnownValidItems( ScanActionVO $action ) :void {
		$optimiser = new Processing\FileScanOptimiser();
		if ( !$optimiser->hasKnownValidFileRecords() ) {
			return;
		}

		$filtered = [];
		$processed = 0;

		foreach ( $action->items as $item ) {
			$processed++;
			$path = \base64_decode( (string)$item, true );
			if ( !\is_string( $path ) || !$optimiser->canSkipKnownValidFile( $path, $action ) ) {
				$filtered[] = $item;
			}

			if ( $processed%self::PRESCAN_HEARTBEAT_ITEM_INTERVAL === 0 ) {
				$action->tickProgress();
			}
		}

		$action->items = $filtered;

		if ( $processed > 0 && $processed%self::PRESCAN_HEARTBEAT_ITEM_INTERVAL !== 0 ) {
			$action->tickProgress();
		}
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
