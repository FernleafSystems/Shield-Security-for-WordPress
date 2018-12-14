<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

use FernleafSystems\Wordpress\Services\Services;

class Select extends BaseQuery {

	/**
	 * @var array
	 */
	protected $aColumnsToSelect;

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
			$this->getDbH()->getTable(),
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
			$aCols = $this->getColumnsToSelect();
			$sSubstitute = sprintf( 'DISTINCT %s', array_pop( $aCols ) );
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
	 * @return EntryVO|\stdClass|null
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
	 * @param string $sColumn
	 * @return array
	 */
	public function getDistinctForColumn( $sColumn ) {
		return $this->reset()
					->addColumnToSelect( $sColumn )
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
	 * @return int|string[]|array[]|EntryVO[]
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
					$mData[ $nKey ] = $this->getDbH()->getVo()->applyFromArray( $oAudit );
				}
			}
		}

		$this->reset();
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
	 * @return $this
	 */
	public function reset() {
		parent::reset();
		return $this->setIsCount( false )
					->setIsDistinct( false )
					->setColumnsToSelect( [] );
	}

	/**
	 * Verifies the given columns are valid and unique
	 * @param string[] $aColumns
	 * @return $this
	 */
	public function setColumnsToSelect( $aColumns ) {
		if ( is_array( $aColumns ) ) {
			$this->aColumnsToSelect = array_intersect(
				$this->getDbH()->getColumnsActual(),
				array_map( 'strtolower', $aColumns )
			);
		}
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