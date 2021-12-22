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
	protected $isCount = false;

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
	 * @param string $col
	 * @return $this
	 */
	public function addColumnToSelect( $col ) {
		$aCols = $this->getColumnsToSelect();
		$aCols[] = $col;
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
		$items = $this->reset()
					  ->addWhereEquals( 'id', $nId )
					  ->query();
		return array_shift( $items );
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
		$cols = $this->getColumnsToSelect();

		if ( $this->isCount() ) {
			$sSubstitute = 'COUNT(*)';
		}
		elseif ( $this->isSum() ) {
			$sSubstitute = sprintf( 'SUM(%s)', array_shift( $cols ) );
		}
		elseif ( $this->isDistinct() && $this->hasColumnsToSelect() ) {
			$sSubstitute = sprintf( 'DISTINCT %s', implode( ',', $cols ) );
		}
		elseif ( $this->hasColumnsToSelect() ) {
			$sSubstitute = implode( ',', $cols );
		}
		elseif ( $this->isCustomSelect() ) {
			$sSubstitute = $this->sCustomSelect;
		}
		else {
			$sSubstitute = '*';
		}
		return $sSubstitute;
	}

	public function sumColumn() :int {
		return (int)$this->setIsCount( true )->query();
	}

	public function count() :int {
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

	protected function getBaseQuery() :string {
		return "SELECT %s FROM `%s` WHERE %s %s";
	}

	public function getColumnsToSelect() :array {
		return is_array( $this->aColumnsToSelect ) ? $this->aColumnsToSelect : [];
	}

	public function getDistinctForColumn( string $col ) :array {
		return $this->reset()
					->addColumnToSelect( $col )
					->setIsDistinct( true )
					->query();
	}

	protected function getDistinct_FilterAndSort( string $col ) :array {
		$a = array_filter( $this->getDistinctForColumn( $col ) );
		natcasesort( $a );
		return $a;
	}

	protected function getSelectDataFormat() :string {
		if ( $this->isResultsAsVo() ) {
			$format = ARRAY_A;
		}
		else {
			$format = in_array( $this->sResultFormat, [ OBJECT_K, ARRAY_A ] ) ? $this->sResultFormat : OBJECT_K;
		}
		return $format;
	}

	protected function hasColumnsToSelect() :bool {
		return count( $this->getColumnsToSelect() ) > 0;
	}

	public function isCount() :bool {
		return (bool)$this->isCount;
	}

	public function isSum() :bool {
		return (bool)$this->bIsSum;
	}

	public function isCustomSelect() :bool {
		return !empty( $this->sCustomSelect );
	}

	public function isDistinct() :bool {
		return (bool)$this->bIsDistinct;
	}

	public function isResultsAsVo() :bool {
		return $this->bResultsAsVo && !$this->isSum();
	}

	/**
	 * Handle COUNT, DISTINCT, & normal SELECT
	 * @return int|string[]|array[]|EntryVO[]|\stdClass[]|mixed
	 */
	public function query() {
		if ( $this->isCount() || $this->isSum() ) {
			$mData = $this->queryVar();
		}
		elseif ( $this->isDistinct() ) {
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
				foreach ( $mData as $nKey => $res ) {
					$mData[ $nKey ] = $this->getDbH()->getVo()->applyFromArray( $res );
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
	 * @return EntryVO|mixed|\stdClass|null
	 */
	public function selectLatestById() {
		return $this->setOrderBy( 'id' )
					->setLimit( 1 )
					->first();
	}

	/**
	 * @return EntryVO|mixed|\stdClass|null
	 */
	public function selectFirstById() {
		return $this->setOrderBy( 'id', 'ASC' )
					->setLimit( 1 )
					->first();
	}

	/**
	 * Verifies the given columns are valid and unique
	 * @param string[] $columns
	 * @return $this
	 */
	public function setColumnsToSelect( array $columns ) {
		$this->aColumnsToSelect = array_intersect(
			$this->getDbH()->getTableSchema()->getColumnNames(),
			array_map( 'strtolower', $columns )
		);
		return $this;
	}

	public function setCustomSelect( string $select ) :self {
		$this->sCustomSelect = $select;
		return $this;
	}

	public function setIsCount( bool $isCount ) :self {
		$this->isCount = $isCount;
		return $this;
	}

	public function setIsSum( bool $sum ) :self {
		$this->bIsSum = $sum;
		return $this;
	}

	public function setIsDistinct( bool $distinct ) :self {
		$this->bIsDistinct = $distinct;
		return $this;
	}

	public function setResultsAsVo( bool $asVO ) :self {
		$this->bResultsAsVo = $asVO;
		return $this;
	}

	public function setSelectResultsFormat( string $format ) :self {
		$this->sResultFormat = $format;
		return $this;
	}
}