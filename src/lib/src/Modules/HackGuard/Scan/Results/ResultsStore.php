<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class ResultsStore
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results
 */
class ResultsStore {

	use Databases\Base\HandlerConsumer;
	use Scans\Common\ScanActionConsumer;

	/**
	 * @param Scans\Base\BaseResultsSet $oToStore
	 */
	public function store( $oToStore ) {
		/** @var Databases\Scanner\Insert $oInsert */
		$oInsert = $this->getDbHandler()->getQueryInserter();
		$aVOs = ( new ConvertBetweenTypes() )
			->setScanActionVO( $this->getScanActionVO() )
			->fromResultsToVOs( $oToStore );
		foreach ( $aVOs as $oVo ) {
			$oInsert->insert( $oVo );
		}
	}
}