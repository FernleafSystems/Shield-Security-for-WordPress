<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

class Scan extends Shield\Scans\Base\Files\BaseFileMapScan {

	/**
	 * @throws \Exception
	 */
	protected function preScan() {
		parent::preScan();

		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();

		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();

		if ( $opts->isOpt( 'optimise_scan_speed', 'Y' ) ) {
			( new Processing\FileScanOptimiser() )
				->setMod( $this->getMod() )
				->filterFilesFromAction( $action );
		}

		$action->confidence_threshold = $opts->getMalConfidenceBoundary();

		$patterns = ( new Utilities\Patterns() )
			->setMod( $this->getMod() )
			->retrieve();
		$action->patterns_simple = $patterns[ 'simple' ];
		$action->patterns_regex = $patterns[ 'regex' ];
		$action->patterns_fullregex = $patterns[ 'fullregex' ] ?? [];
	}

	/**
	 * @return ScanFromFileMap
	 */
	protected function getScanFromFileMap() {
		return ( new ScanFromFileMap() )
			->setMod( $this->getMod() )
			->setScanActionVO( $this->getScanActionVO() );
	}

	protected function postScan() {
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();

		if ( $opts->isOpt( 'optimise_scan_speed', 'Y' ) ) {
			/** @var ScanActionVO $action */
			$action = $this->getScanActionVO();
			( new Processing\FileScanOptimiser() )
				->setMod( $this->getMod() )
				->addFilesFromAction( $action );
		}
	}
}