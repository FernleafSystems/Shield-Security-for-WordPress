<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class ResultsDelete
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results
 */
class ResultsDelete {

	use Databases\Base\HandlerConsumer;

	/**
	 * @param Scans\Base\BaseResultsSet $oToDelete
	 */
	public function delete( $oToDelete ) {
		( new Clean() )
			->setDbHandler( $this->getDbHandler() )
			->deleteResults( $oToDelete );
	}
}
