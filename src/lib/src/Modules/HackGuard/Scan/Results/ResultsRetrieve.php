<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class ResultsRetrieve
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ScanQueue
 */
class ResultsRetrieve {

	use Databases\Base\HandlerConsumer,
		Scans\Common\ScanActionConsumer;

	/**
	 * @return Scans\Base\BaseResultsSet|mixed
	 */
	public function retrieve() {
		/** @var Databases\Scanner\Select $oSelector */
		$oSelector = $this->getDbHandler()->getQuerySelector();
		$oScan = $this->getScanActionVO();
		return ( new ConvertBetweenTypes() )
			->setScanActionVO( $oScan )
			->fromVOsToResultsSet( $oSelector->forScan( $oScan->scan ) );
	}
}
