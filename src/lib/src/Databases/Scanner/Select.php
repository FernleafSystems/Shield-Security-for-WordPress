<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Services\Services;

class Select extends Base\Select {

	/**
	 * @param string $sHash
	 * @return $this
	 */
	public function filterByHash( $sHash ) {
		if ( !empty( $sHash ) ) {
			$this->addWhereEquals( 'hash', $sHash );
		}
		return $this;
	}

	/**
	 * @return $this
	 */
	public function filterByIgnored() {
		return $this->addWhereNewerThan( 0, 'ignored_at' );
	}

	/**
	 * @return $this
	 */
	public function filterByNotIgnored() {
		return $this->addWhereEquals( 'ignored_at', 0 );
	}

	/**
	 * @param int $nInterval
	 * @return $this
	 */
	public function filterByNotRecentlyNotified( $nInterval = null ) {
		if ( is_null( $nInterval ) ) {
			$nInterval = WEEK_IN_SECONDS;
		}
		return $this->addWhereOlderThan( Services::Request()->ts() - $nInterval, 'notified_at' );
	}

	/**
	 * @param int $nInterval
	 * @return $this
	 */
	public function filterByIsRecentlyNotified( $nInterval = null ) {
		if ( is_null( $nInterval ) ) {
			$nInterval = WEEK_IN_SECONDS;
		}
		return $this->addWhereNewerThan( Services::Request()->ts() - $nInterval, 'notified_at' );
	}

	/**
	 * @param string $sScan
	 * @return $this
	 */
	public function filterByScan( $sScan ) {
		if ( !empty( $sScan ) ) {
			$this->addWhereEquals( 'scan', strtolower( $sScan ) );
		}
		return $this;
	}

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