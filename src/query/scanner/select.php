<?php

if ( class_exists( 'ICWP_WPSF_Query_Scanner_Select', false ) ) {
	return;
}

require_once( dirname( __DIR__ ).'/base/select.php' );

use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Query_Scanner_Select extends ICWP_WPSF_Query_BaseSelect {

	/**
	 * @return string[]
	 */
	public function getDistinctSeverity() {
		return $this->getDistinct_FilterAndSort( 'severity' );
	}

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
	public function filterByNotIgnored() {
		if ( !empty( $sHash ) ) {
			$this->addWhereOlderThan( Services::Request()->ts(), 'ignore_until' );
		}
		return $this;
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
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO[]|stdClass[]
	 */
	public function forScan( $sScan ) {
		return $this->reset()
					->filterByScan( $sScan )
					->query();
	}

	/**
	 * @return int|stdClass[]|\FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO[]
	 */
	public function query() {
		return parent::query();
	}

	/**
	 * @return string
	 */
	protected function getVoName() {
		return 'ICWP_WPSF_ScannerEntryVO';
	}
}