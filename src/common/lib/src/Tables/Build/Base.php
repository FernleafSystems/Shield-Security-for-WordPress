<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Services\Services;

class Base {

	/**
	 * @var \ICWP_WPSF_Query_BaseSelect
	 */
	protected $oQuerySelector;

	/**
	 * @var array
	 */
	protected $aBuildParams;

	public function buildTable() {
		$this->applyDefaultParameters()
			 ->applyQueryFilters();
	}

	protected function applyDefaultParameters() {
		$aParams = $this->getFilteredAllowedBuildParams();
		$this->getQuerySelector()
			 ->setPage( $aParams[ 'paged' ] )
			 ->setOrderBy( $aParams[ 'orderby' ], $aParams[ 'order' ] )
			 ->setLimit( $aParams[ 'limit' ] )
			 ->setResultsAsVo( true );
		return $this;
	}

	/**
	 */
	protected function applyQueryFilters() {
		$aParams = $this->getFilteredAllowedBuildParams();
		return $this;
	}

	/**
	 * Filters all $_POSTed parameters against a list of allowable parameters
	 * @return array
	 */
	protected function getFilteredAllowedBuildParams() {

		if ( empty( $this->aBuildParams ) ) {

			$aPostedParams = array_merge( $_POST, array_map( 'trim', $this->getFormParams() ) );
			$aFilteredPostedParams = array_intersect_key(
				$aPostedParams,
				array_flip( array_merge(
					array_keys( $this->getDefaultParams() ), array_keys( $this->getCustomParams() )
				) )
			);

			$this->aBuildParams = array_merge( $this->getDefaultParams(), $aFilteredPostedParams );
		}

		return $this->aBuildParams;
	}

	/**
	 * @return array
	 */
	protected function getFormParams() {
		parse_str( Services::Request()->post( 'form_params', '' ), $aFormParams );
		return $aFormParams;
	}

	/**
	 * @return array
	 */
	protected function getDefaultParams() {
		return array(
			'paged'   => 1,
			'order'   => 'DESC',
			'orderby' => 'created_at',
			'limit'   => 25,
		);
	}

	/**
	 * Override to allow other parameter keys for building the table
	 * @return array
	 */
	protected function getCustomParams() {
		return array();
	}

	/**
	 * @return \ICWP_WPSF_Query_BaseSelect
	 */
	public function getQuerySelector() {
		return $this->oQuerySelector;
	}

	/**
	 * @param \ICWP_WPSF_Query_BaseSelect $oQuerySelector
	 * @return $this
	 */
	public function setQuerySelector( $oQuerySelector ) {
		$this->oQuerySelector = $oQuerySelector;
		return $this;
	}
}