<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\ScanControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class ResultsStore
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results
 */
class ResultsStore {

	use ScanControllerConsumer;

	/**
	 * @param Scans\Base\ResultsSet $resultsToStore
	 */
	public function store( $resultsToStore ) {
		$scanCon = $this->getScanController();
		$inserter = $scanCon->getScanResultsDbHandler()
							->getQueryInserter();
		$VOs = ( new ConvertBetweenTypes() )
			->setScanController( $scanCon )
			->fromResultsToVOs( $resultsToStore );
		foreach ( $VOs as $vo ) {
			$inserter->insert( $vo );
		}
	}
}