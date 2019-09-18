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
	protected $bIsSum = false;

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
	protected $sCustomSelect;

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
	 * @return array[]|int|string[]|EntryVO[]|mixed
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
		$aCols = $this->getColumnsToSelect();

		if ( $this->isCount() ) {
			$sSubstitute = 'COUNT(*)';
		}
		else if ( $this->isSum() ) {
			$sSubstitute = sprintf( 'SUM(%s)', array_shift( $aCols ) );
		}
		else if ( $this->isDistinct() && $this->hasColumnsToSelect() ) {
			$sSubstitute = sprintf( 'DISTINCT %s', implode( ',', $aCols ) );
		}
		else if ( $this->hasColumnsToSelect() ) {
			$sSubstitute = implode( ',', $aCols );
		}
		else if ( $this->isCustomSelect() ) {
			$sSubstitute = $this->sCustomSelect;
		}
		else {
			$sSubstitute = '*';
		}
		return $sSubstitute;
	}

	/**
	 * @return int
	 */
	public function count() {
		return (int)$this->setIsCount( true )->query();
	}

	/**
	 * @return int
	 */
	public function sum() {
		return $this->setIsSum( true )->query();
	}

	/**
	 * @return EntryVO|\stdClass|mixed|null
	 */
	public function first() {
		$aR = $this->setLimit( 1 )->query();
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
		return is_array( $this->aColumnsToSelect ) ? $this->aColumnsToSelect : [];
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
			$sForm = in_array( $this->sResultFormat, [ OBJECT_K, ARRAY_A ] ) ? $this->sResultFormat : OBJECT_K;
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
	public function isSum() {
		return (bool)$this->bIsSum;
	}

	/**
	 * @return bool
	 */
	public function isCustomSelect() {
		return !empty( $this->sCustomSelect );
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
		return (bool)$this->bResultsAsVo && !$this->isSum();
	}

	/**
	 * Handle COUNT, DISTINCT, & normal SELECT
	 * @return int|string[]|array[]|EntryVO[]|mixed
	 */
	public function query() {
		if ( $this->isCount() || $this->isSum() ) {
			$mData = $this->queryVar();
		}
		else if ( $this->isDistinct() ) {
			$mData = $this->queryDistinct();
			if ( is_array( $mData ) ) {
				$mData = array_map( function ( $aRecord ) {
					return array_shift( $aRecord );
				}, $mData );
			}
			else {
				$mData = [];
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
	protected function queryVar() {
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
					->setGroupBy( '' )
					->setSelectResultsFormat( '' )
					->setCustomSelect( '' )
					->setColumnsToSelect( [] )
					->clearWheres();
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
	 * @param string $sSelect
	 * @return $this
	 */
	public function setCustomSelect( $sSelect ) {
		$this->sCustomSelect = $sSelect;
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
	 * @param bool $bSum
	 * @return $this
	 */
	public function setIsSum( $bSum ) {
		$this->bIsSum = $bSum;
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