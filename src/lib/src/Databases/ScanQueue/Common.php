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
	 * @return $this
	 */
	public function filterByNotFinished() {
		return $this->addWhereEquals( 'finished_at', 0 );
	}

	/**
	 * @return $this
	 */
	public function filterByNotStarted() {
		return $this->addWhereEquals( 'started_at', 0 );
	}

	/**
	 * @return $this
	 */
	public function filterByFinished() {
		return $this->addWhereNewerThan( 0, 'finished_at' );
	}

	/**
	 * @return $this
	 */
	public function filterByStarted() {
		return $this->addWhereNewerThan( 0, 'started_at' );
	}

	/**
	 * @param string $scan
	 * @return bool
	 */
	public function forScan( $scan ) {
		$this->reset();
		return $this->filterByScan( $scan )
					->query();
	}
}