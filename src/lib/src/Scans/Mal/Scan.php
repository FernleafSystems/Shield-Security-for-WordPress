<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

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
}