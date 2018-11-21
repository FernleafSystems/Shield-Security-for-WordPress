<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Plugin\Shield\Tables;

class BaseBuild {

	use Shield\Modules\ModConsumer;

	/**
	 * @var Shield\Databases\Base\Select
	 */
	protected $oQuerySelector;

	/**
	 * @var array
	 */
	protected $aBuildParams;

	public function buildTable() {
		$aParams = $this->getParams();
		$nPerPage = isset( $aParams[ 'limit' ] ) ? $aParams[ 'limit' ] : 25;

		$oTable = $this->getTableRenderer()
					   ->setItemEntries( $this->getEntriesFormatted() )
					   ->setPerPage( $nPerPage )
					   ->prepare_items();
		ob_start();
		$oTable->display();
		return ob_get_clean();
	}

	/**
	 * @return array[]|int|string[]
	 */
	protected function getEntriesFormatted() {
		return $this->getEntriesRaw();
	}

	/**
	 * @param int $nTimestamp
	 * @return string
	 */
	protected function formatTimestampField( $nTimestamp ) {
		return ( new \Carbon\Carbon() )->setTimestamp( $nTimestamp )->diffForHumans()
			   .'<br/><span class="timestamp-small">'
			   .Services::WpGeneral()->getTimeStringForDisplay( $nTimestamp ).'</span>';
	}

	/**
	 * @return array[]|int|string[]|Shield\Databases\Base\EntryVO[]
	 */
	protected function getEntriesRaw() {
		$aEntries = $this->applyDefaultParameters()
						 ->applyQueryFilters()
						 ->getQuerySelector()
						 ->query();
		return $this->postSelectEntriesFilter( $aEntries );
	}

	/**
	 * Override this to filter entries that cannot be filtered using SQL WHERE
	 * @param array[] $aEntries
	 * @return array[]
	 */
	protected function postSelectEntriesFilter( $aEntries ) {
		return $aEntries;
	}

	/**
	 * @return Tables\Render\Base
	 */
	protected function getTableRenderer() {
		return new Tables\Render\Base();
	}

	/**
	 * @return $this
	 */
	protected function applyDefaultParameters() {
		$aParams = $this->getParams();
		$oSelect = $this->getQuerySelector();

		if ( isset( $aParams[ 'paged' ] ) ) {
			$oSelect->setPage( $aParams[ 'paged' ] );
		}
		if ( isset( $aParams[ 'orderby' ] ) && isset( $aParams[ 'order' ] ) ) {
			$oSelect->setOrderBy( $aParams[ 'orderby' ], $aParams[ 'order' ] );
		}
		if ( isset( $aParams[ 'limit' ] ) ) {
			$oSelect->setLimit( $aParams[ 'limit' ] );
		}
		$oSelect->setResultsAsVo( true );
		return $this;
	}

	/**
	 * Override this to apply table-specific query filters.
	 * @return $this
	 */
	protected function applyQueryFilters() {
		return $this;
	}

	/**
	 * @return array
	 */
	protected function getParams() {
		if ( empty( $this->aBuildParams ) ) {
			$this->aBuildParams = array_merge(
				$this->getParamDefaults(),
				array_merge( $_POST, $this->getFormParams() )
			);
		}
		return $this->aBuildParams;
	}

	/**
	 * @return array
	 */
	private function getFormParams() {
		parse_str( Services::Request()->post( 'form_params', '' ), $aFormParams );
		return array_map( 'trim', $aFormParams );
	}

	/**
	 * @return array
	 */
	private function getParamDefaults() {
		return array_merge(
			array(
				'paged'   => 1,
				'order'   => 'DESC',
				'orderby' => 'created_at',
				'limit'   => 25,
			),
			$this->getCustomParams()
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
	 * @return Shield\Databases\Base\Select
	 */
	public function getQuerySelector() {
		return $this->oQuerySelector;
	}

	/**
	 * @param Shield\Databases\Base\Select $oQuerySelector
	 * @return $this
	 */
	public function setQuerySelector( $oQuerySelector ) {
		$this->oQuerySelector = $oQuerySelector;
		return $this;
	}
}