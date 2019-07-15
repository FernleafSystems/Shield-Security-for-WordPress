<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

use FernleafSystems\Wordpress\Plugin\Shield;

/**
 * Class ScannerFromFileMap
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal
 */
class ScannerFromDirectory extends ScannerFromFileMap {

	/**
	 * @return ResultsSet
	 */
	public function run() {
		/** @var Shield\Modules\HackGuard\Options $oOpts */
		$oOpts = $this->getMod()->getOptions();

		$this->setFileMap(
			( new BuildFileMap() )
				->setWhitelistedPaths( $oOpts->getMalwareWhitelistPaths() )
				->build()
		);

		return parent::run();
	}
}