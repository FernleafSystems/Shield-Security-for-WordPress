<?php

if ( class_exists( 'ICWP_WPSF_Query_Scanner_Delete', false ) ) {
	return;
}

require_once( dirname( __DIR__ ).'/base/delete.php' );

class ICWP_WPSF_Query_Scanner_Delete extends ICWP_WPSF_Query_BaseDelete {

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
		return $this->reset()
					->filterByScan( $sScan )
					->query();
	}

	/**
	 * @return ICWP_WPSF_Query_Scanner_Select
	 */
	protected function getSelector() {
		require_once( dirname( __FILE__ ).'/select.php' );
		$oCounter = new ICWP_WPSF_Query_Scanner_Select();
		return $oCounter->setTable( $this->getTable() );
	}
}