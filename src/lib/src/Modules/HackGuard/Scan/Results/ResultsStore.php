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
	 * @param Scans\Base\BaseResultsSet $oToStore
	 */
	public function store( $oToStore ) {
		$sSCon = $this->getScanController();
		$oInsert = $sSCon->getScanResultsDbHandler()
						 ->getQueryInserter();
		$aVOs = ( new ConvertBetweenTypes() )
			->setScanActionVO( $sSCon->getScanActionVO() )
			->fromResultsToVOs( $oToStore );
		foreach ( $aVOs as $oVo ) {
			$oInsert->insert( $oVo );
		}
	}
}