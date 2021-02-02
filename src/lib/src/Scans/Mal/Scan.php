<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

/**
 * Class Scan
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal
 */
class Scan extends Shield\Scans\Base\Files\BaseFileMapScan {

	/**
	 * @throws \Exception
	 */
	protected function preScan() {
		parent::preScan();

		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();

		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();

		$action->confidence_threshold = $oOpts->getMalConfidenceBoundary();

		$patterns = ( new Utilities\Patterns() )
			->setMod( $this->getMod() )
			->retrieve();
		$action->patterns_simple = $patterns[ 'simple' ];
		$action->patterns_regex = $patterns[ 'regex' ];
		error_log( var_export( $action->patterns_simple, true ) );
		error_log( var_export( $action->patterns_regex, true ) );
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