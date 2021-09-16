<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;

use FernleafSystems\Wordpress\Services\Services;

trait Common {

	/**
	 * @param string $hash
	 * @return $this
	 */
	public function filterByHash( string $hash ) {
		if ( !empty( $hash ) ) {
			$this->addWhereEquals( 'hash', $hash );
		}
		return $this;
	}

	/**
	 * @param string[] $hashes
	 * @return $this
	 */
	public function filterByHashes( $hashes ) {
		return $this->addWhereIn( 'hash', $hashes );
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
	 * @return $this
	 */
	public function filterByNotified() {
		return $this->addWhereOlderThan( 0, 'notified_at' );
	}

	/**
	 * @return $this
	 */
	public function filterByNotNotified() {
		return $this->addWhereEquals( 'notified_at', 0 );
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
			$this->filterByScans( [ $sScan ] );
		}
		return $this;
	}

	/**
	 * @param string[] $aScans
	 * @return $this
	 */
	public function filterByScans( $aScans ) {
		return $this->addWhereIn( 'scan', array_map( 'strtolower', $aScans ) );
	}
}