<?php

if ( class_exists( 'ICWP_WPSF_Query_BaseSelect', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/query.php' );

class ICWP_WPSF_Query_BaseSelect extends ICWP_WPSF_Query_BaseQuery {

	/**
	 * @var array
	 */
	protected $aColumnsToSelect;

	/**
	 * @var array
	 */
	protected $aColumnsDefinition;

	/**
	 * @param string $sCol
	 * @return $this
	 */
	public function addColumnToSelect( $sCol ) {
		$aCols = $this->getColumnsToSelect();
		$aCols[] = $sCol;
		return $this->setColumnsToSelect( $aCols );
	}

	/**
	 * @return stdClass[]
	 */
	public function all() {
		return $this->reset()
					->query();
	}

	/**
	 * @param int $nId
	 * @return stdClass
	 */
	public function byId( $nId ) {
		$aItems = $this->reset()
					   ->addWhereEquals( 'id', $nId )
					   ->query();
		return array_shift( $aItems );
	}

	/**
	 * @return ICWP_WPSF_BaseEntryVO|stdClass|null
	 */
	public function first() {
		$aR = $this->query();
		return empty( $aR ) ? null : array_shift( $aR );
	}

	/**
	 * @return string
	 */
	protected function getBaseQuery() {
		return "SELECT %s FROM `%s` WHERE %s %s";
	}

	/**
	 * @return string
	 */
	public function buildQuery() {
		return sprintf( $this->getBaseQuery(),
			$this->buildSelect(),
			$this->getTable(),
			$this->buildWhere(),
			$this->buildExtras()
		);
	}

	/**
	 * @return string
	 */
	protected function buildSelect() {
		return $this->hasColumnsToSelect() ? implode( ',', $this->getColumnsToSelect() ) : '*';
	}

	/**
	 * @return array
	 */
	public function getColumnsToSelect() {
		return is_array( $this->aColumnsToSelect ) ? $this->aColumnsToSelect : array();
	}

	/**
	 * @return string[]
	 */
	public function getColumnsDefinition() {
		return is_array( $this->aColumnsDefinition ) ? $this->aColumnsDefinition : array();
	}

	/**
	 * @return bool
	 */
	protected function hasColumnsToSelect() {
		return ( count( $this->getColumnsToSelect() ) > 0 );
	}

	/**
	 * @return stdClass[]
	 */
	public function query() {
		return $this->loadDbProcessor()
					->selectCustom( $this->buildQuery(), OBJECT_K );
	}

	/**
	 * Verifies the given columns are valid and unique
	 * @param string[] $aColumns
	 * @return $this
	 */
	public function setColumnsToSelect( $aColumns ) {
		if ( is_array( $aColumns ) ) {
			$aColumns = array_filter( array_map( 'trim', $aColumns ) );
			$aDef = $this->getColumnsDefinition();
			if ( !empty( $aDef ) ) {
				foreach ( $aColumns as $nKey => $sCol ) {
					if ( !in_array( $sCol, $aDef ) ) {
						unset( $aColumns[ $nKey ] );
					}
				}
			}
			$this->aColumnsToSelect = array_unique( $aColumns );
		}
		return $this;
	}

	/**
	 * @param string[] $aColumns
	 * @return $this
	 */
	public function setColumnsDefinition( $aColumns ) {
		$this->aColumnsDefinition = $aColumns;
		return $this;
	}
}