<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\ScanControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class ResultsRetrieve
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results
 */
class ResultsRetrieve {

	use ScanControllerConsumer;

	/**
	 * @return Scans\Base\BaseResultsSet|mixed
	 */
	public function retrieve() {
		$oSCon = $this->getScanController();
		/** @var Databases\Scanner\Select $oSelector */
		$oSelector = $oSCon->getScanResultsDbHandler()->getQuerySelector();
		return ( new ConvertBetweenTypes() )
			->setScanController( $oSCon )
			->fromVOsToResultsSet( $oSelector->forScan( $oSCon->getSlug() ) );
	}
}
