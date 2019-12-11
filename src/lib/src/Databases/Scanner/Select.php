<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Services\Services;

class Select extends Base\Select {

	use Common;

	/**
	 * @param int $nNotifiedInterval
	 * @return $this
	 */
	public function filterForCron( $nNotifiedInterval ) {
		return $this->filterByNotRecentlyNotified( $nNotifiedInterval )
					->filterByNotIgnored();
	}

	/**
	 * @return string[]
	 */
	public function getDistinctSeverity() {
		return $this->getDistinct_FilterAndSort( 'severity' );
	}

	/**
	 * @param string $sScan
	 * @return int
	 */
	public function countForScan( $sScan ) {
		return $this->reset()
					->filterByNotIgnored()
					->filterByScan( $sScan )
					->count();
	}

	/**
	 * @param string $sScan
	 * @return EntryVO[]
	 */
	public function forScan( $sScan ) {
		return $this->reset()
					->filterByScan( $sScan )
					->query();
	}
}