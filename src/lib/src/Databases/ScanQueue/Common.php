<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\ScanQueue;

/**
 * Trait Filters
 * @package FernleafSystems\Wordpress\Plugin\Shield\Databases\ScanQueue
 */
trait Common {

	/**
	 * @param string $sScan
	 * @return $this
	 */
	public function filterByScan( $sScan ) {
		if ( !empty( $sScan ) ) {
			$this->addWhereEquals( 'scan', $sScan );
		}
		return $this;
	}

	/**
	 * @param string $sScan
	 * @return bool
	 */
	public function forScan( $sScan ) {
		$this->reset();
		return $this->filterByScan( $sScan )
					->query();
	}
}