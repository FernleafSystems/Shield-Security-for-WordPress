<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Select extends Base\Select {

	use Common;

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