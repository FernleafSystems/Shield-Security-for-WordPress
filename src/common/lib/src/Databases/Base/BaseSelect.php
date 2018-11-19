<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

use FernleafSystems\Wordpress\Services\Services;

class BaseSelect extends BaseQuery {

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
	 * @var bool
	 */
	protected $bIsDistinct = false;

	/**
	 * @var bool
	 */
	protected $bResultsAsVo;

	/**
	 * @var string
	 */
	protected $sResultFormat;

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
	 * @return array[]|int|string[]
	 */
	public function all() {
		return $this->reset()->query();
	}

	/**
	 * @param int $nId
	 * @return \stdClass
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
		else if ( $this->isDistinct() && $this->hasColumnsToSelect() ) {
			$sSubstitute = sprintf( 'DISTINCT %s', $this->getColumnsToSelect()[ 0 ] );
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
	 * @return BaseEntryVO|\stdClass|null
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
	 * @param string $sColumn
	 * @return array
	 */
	public function getDistinctForColumn( $sColumn ) {
		return $this->addColumnToSelect( $sColumn )
					->setIsDistinct( true )
					->query();
	}

	/**
	 * @param string $sColumn
	 * @return array
	 */
	protected function getDistinct_FilterAndSort( $sColumn ) {
		$a = array_filter( $this->getDistinctForColumn( $sColumn ) );
		natcasesort( $a );
		return $a;
	}

	/**
	 * @return string
	 */
	protected function getSelectDataFormat() {
		if ( $this->isResultsAsVo() ) {
			$sForm = ARRAY_A;
		}
		else {
			$sForm = in_array( $this->sResultFormat, array( OBJECT_K, ARRAY_A ) ) ? $this->sResultFormat : OBJECT_K;
		}
		return $sForm;
	}

	/**
	 * @return BaseEntryVO
	 */
	public function getVo() {
		return new BaseEntryVO();
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
	 * @return bool
	 */
	public function isDistinct() {
		return (bool)$this->bIsDistinct;
	}

	/**
	 * @return bool
	 */
	public function isResultsAsVo() {
		return (bool)$this->bResultsAsVo;
	}

	/**
	 * Handle COUNT, DISTINCT, & normal SELECT
	 * @return int|string[]|array[]
	 */
	public function query() {
		if ( $this->isCount() ) {
			$mData = $this->queryCount();
		}
		else if ( $this->isDistinct() ) {
			$mData = $this->queryDistinct();
			if ( is_array( $mData ) ) {
				$mData = array_map( function ( $aRecord ) {
					return array_shift( $aRecord );
				}, $mData );
			}
			else {
				$mData = array();
			}
		}
		else {
			$mData = $this->querySelect();
			if ( $this->isResultsAsVo() ) {
				foreach ( $mData as $nKey => $oAudit ) {
					$mData[ $nKey ] = $this->getVo()->setRawData( $oAudit );
				}
			}
		}

		return $mData;
	}

	/**
	 * @return array[]
	 */
	protected function querySelect() {
		return Services::WpDb()->selectCustom( $this->buildQuery(), $this->getSelectDataFormat() );
	}

	/**
	 * @return int
	 */
	protected function queryCount() {
		return Services::WpDb()->getVar( $this->buildQuery() );
	}

	/**
	 * @return array[]
	 */
	protected function queryDistinct() {
		return Services::WpDb()->selectCustom( $this->buildQuery() );
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

	/**
	 * @param bool $bIsDistinct
	 * @return $this
	 */
	public function setIsDistinct( $bIsDistinct ) {
		$this->bIsDistinct = $bIsDistinct;
		return $this;
	}

	/**
	 * @param bool $bResultsAsVo
	 * @return $this
	 */
	public function setResultsAsVo( $bResultsAsVo ) {
		$this->bResultsAsVo = $bResultsAsVo;
		return $this;
	}

	/**
	 * @param string $sFormat
	 * @return $this
	 */
	public function setSelectResultsFormat( $sFormat ) {
		$this->sResultFormat = $sFormat;
		return $this;
	}
}