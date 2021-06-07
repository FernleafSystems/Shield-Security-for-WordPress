<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Select extends Base\Select {

	use Common;

	public function countForScan( string $scan ) :int {
		return $this->reset()
					->filterByNotIgnored()
					->filterByScan( $scan )
					->count();
	}

	/**
	 * @param string $scan
	 * @return EntryVO[]
	 */
	public function forScan( $scan ) {
		return $this->reset()
					->filterByScan( $scan )
					->query();
	}
}