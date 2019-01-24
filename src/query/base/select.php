<?php

/**
 * @deprecated v7.0.0
 * Class ICWP_WPSF_Query_BaseSelect
 */
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
	 * @var bool
	 */
	protected $bIsCount = false;

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
		return $this->reset()->query();
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
		$sSubstitute = '*';
		if ( $this->isCount() ) {
			$sSubstitute = 'COUNT(*)';
		}
		else if ( $this->hasColumnsToSelect() ) {
			$sSubstitute = implode( ',', $this->getColumnsToSelect() );
		}
		return $sSubstitute;
	}

	/**
	 * @return int
	 */
	public function count() {
		return $this->setIsCount( true )->query();
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
	 * @return ICWP_WPSF_BaseEntryVO
	 */
	public function getVo() {
		$sClass = $this->getVoName();
		if ( !class_exists( $sClass ) ) {
			require_once( dirname( dirname( __FILE__ ) ).'/VOs/'.$sClass.'.php' );
		}
		return new $sClass();
	}

	/**
	 * @return string
	 */
	protected function getVoName() {
		return 'ICWP_WPSF_BaseEntryVO';
	}

	/**
	 * @return bool
	 */
	protected function hasColumnsToSelect() {
		return ( count( $this->getColumnsToSelect() ) > 0 );
	}

	/**
	 * @return bool
	 */
	public function isCount() {
		return (bool)$this->bIsCount;
	}

	/**
	 * @return stdClass[]|int
	 */
	public function query() {
		$mData = $this->isCount() ? $this->queryCount() : $this->querySelect();

		if ( !$this->isCount() && $this->isResultsAsVo() ) {
			foreach ( $mData as $nKey => $oAudit ) {
				$mData[ $nKey ] = $this->getVo()->setRawData( $oAudit );
			}
		}

		return $mData;
	}

	/**
	 * @return stdClass[]
	 */
	protected function querySelect() {
		return $this->loadDbProcessor()
					->selectCustom( $this->buildQuery(), OBJECT_K );
	}

	/**
	 * @return int
	 */
	protected function queryCount() {
		return $this->loadDbProcessor()->getVar( $this->buildQuery() );
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

	/**
	 * @param bool $bIsCount
	 * @return $this
	 */
	public function setIsCount( $bIsCount ) {
		$this->bIsCount = $bIsCount;
		return $this;
	}
}