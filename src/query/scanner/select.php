<?php

if ( class_exists( 'ICWP_WPSF_Query_Scanner_Select', false ) ) {
	return;
}

require_once( dirname( __DIR__ ).'/base/select.php' );

class ICWP_WPSF_Query_Scanner_Select extends ICWP_WPSF_Query_BaseSelect {

	/**
	 * @return string[]
	 */
	public function getDistinctSeverity() {
		return $this->getDistinct_FilterAndSort( 'severity' );
	}

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
	 * @return ICWP_WPSF_ScannerEntryVO[]|stdClass[]
	 */
	public function forScan( $sScan ) {
		return $this->reset()
					->filterByScan( $sScan )
					->query();
	}

	/**
	 * @return int|stdClass[]|ICWP_WPSF_ScannerEntryVO[]
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