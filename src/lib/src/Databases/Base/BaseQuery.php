<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

use Carbon\Carbon;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseQuery {

	/**
	 * @var Handler
	 */
	protected $oDbH;

	/**
	 * @var array
	 */
	protected $aWheres;

	/**
	 * @var bool
	 */
	protected $bExcludeDeleted;

	/**
	 * @var int
	 */
	protected $nLimit = 0;

	/**
	 * @var int
	 */
	protected $nPage;

	/**
	 * @var string
	 */
	protected $sOrderBy;

	/**
	 * @var string
	 */
	protected $sGroupBy;

	public function __construct() {
		$this->customInit();
	}

	/**
	 * override to add custom init actions
	 */
	protected function customInit() {
	}

	/**
	 * @param string       $sColumn
	 * @param string|array $mValue
	 * @param string       $sOperator
	 * @return $this
	 */
	public function addWhere( $sColumn, $mValue, $sOperator = '=' ) {
		if ( !$this->isValidComparisonOperator( $sOperator ) ) {
			return $this; // Exception?
		}

		if ( is_array( $mValue ) ) {
			$mValue = array_map( 'esc_sql', $mValue );
			$mValue = "('".implode( "','", $mValue )."')";
		}
		else {
			$mValue = esc_sql( $mValue );

			if ( strcasecmp( $sOperator, 'LIKE' ) === 0 ) {
				$mValue = sprintf( '%%%s%%', $mValue );
			}
			if ( is_string( $mValue ) ) {
				$mValue = sprintf( "'%s'", $mValue );
			}
		}

		$aWhere = $this->getWheres();
		$aWhere[] = sprintf( '`%s` %s %s', esc_sql( $sColumn ), $sOperator, $mValue );
		return $this->setWheres( $aWhere );
	}

	/**
	 * @param string $sColumn
	 * @param mixed  $mValue
	 * @return $this
	 */
	public function addWhereEquals( $sColumn, $mValue ) {
		return $this->addWhere( $sColumn, $mValue, '=' );
	}

	/**
	 * @param string $sColumn
	 * @param array  $aValues
	 * @return $this
	 */
	public function addWhereIn( $sColumn, $aValues ) {
		if ( !empty( $aValues ) && is_array( $aValues ) ) {
			$this->addWhere( $sColumn, $aValues, 'IN' );
		}
		return $this;
	}

	/**
	 * @param int    $nNewerThanTimeStamp
	 * @param string $sColumn
	 * @return $this
	 */
	public function addWhereNewerThan( $nNewerThanTimeStamp, $sColumn = 'created_at' ) {
		return $this->addWhere( $sColumn, $nNewerThanTimeStamp, '>' );
	}

	/**
	 * @param int    $nOlderThanTimeStamp
	 * @param string $sColumn
	 * @return $this
	 */
	public function addWhereOlderThan( $nOlderThanTimeStamp, $sColumn = 'created_at' ) {
		return $this->addWhere( $sColumn, $nOlderThanTimeStamp, '<' );
	}

	/**
	 * @param string $sColumn
	 * @param mixed  $mValue
	 * @return $this
	 */
	public function addWhereSearch( $sColumn, $mValue ) {
		return $this->addWhere( $sColumn, $mValue, 'LIKE' );
	}

	/**
	 * @return string
	 */
	public function buildExtras() {
		$aExtras = array_filter(
			[
				$this->getGroupBy(),
				$this->getOrderBy(),
				$this->buildLimitPhrase(),
				$this->buildOffsetPhrase(),
			]
		);
		return implode( "\n", $aExtras );
	}

	/**
	 * @return string
	 */
	public function buildLimitPhrase() {
		return $this->hasLimit() ? sprintf( 'LIMIT %s', $this->getLimit() ) : '';
	}

	/**
	 * @return string
	 */
	protected function buildOffsetPhrase() {
		return $this->hasLimit() ? sprintf( 'OFFSET %s', $this->getOffset() ) : '';
	}

	/**
	 * @return $this
	 */
	public function clearWheres() {
		return $this->setWheres( [] );
	}

	/**
	 * @return int
	 */
	protected function getOffset() {
		return (int)$this->getLimit()*( $this->getPage() - 1 );
	}

	/**
	 * @return string
	 */
	public function buildWhere() {

		$aParts = $this->getWheres();
		if ( $this->isExcludeDeleted() ) {
			$aParts[] = '`deleted_at`=0';
		}

		return implode( ' AND ', $aParts );
	}

	/**
	 * @return string
	 */
	public function buildQuery() {
		return sprintf( $this->getBaseQuery(),
			$this->getDbH()->getTable(),
			$this->buildWhere(),
			$this->buildExtras()
		);
	}

	/**
	 * @param int    $nTs
	 * @param string $sComparison
	 * @return $this
	 */
	public function filterByCreatedAt( $nTs, $sComparison ) {
		if ( !preg_match( '#[^=<>]#', $sComparison ) && is_numeric( $nTs ) ) {
			$this->addWhere( 'created_at', (int)$nTs, $sComparison );
		}
		return $this;
	}

	/**
	 * @param int $nTs
	 * @return $this
	 */
	public function filterByBoundary_Day( $nTs ) {
		$oCbn = ( new Carbon() )->setTimestamp( $nTs );
		return $this->filterByCreatedAt( $oCbn->endOfDay()->timestamp, '<=' )
					->filterByCreatedAt( $oCbn->startOfDay()->timestamp, '>=' );
	}

	/**
	 * @param int $nTs
	 * @return $this
	 */
	public function filterByBoundary_Hour( $nTs ) {
		$oCbn = ( new Carbon() )->setTimestamp( $nTs );
		return $this->filterByCreatedAt( $oCbn->endOfHour()->timestamp, '<=' )
					->filterByCreatedAt( $oCbn->startOfHour()->timestamp, '>=' );
	}

	/**
	 * @param int $nTs
	 * @return $this
	 */
	public function filterByBoundary_Month( $nTs ) {
		$oCbn = ( new Carbon() )->setTimestamp( $nTs );
		return $this->filterByCreatedAt( $oCbn->startOfMonth()->timestamp, '>=' )
					->filterByCreatedAt( $oCbn->endOfMonth()->timestamp, '<=' );
	}

	/**
	 * @param int $nTs
	 * @return $this
	 */
	public function filterByBoundary_Week( $nTs ) {
		$oCbn = ( new Carbon() )->setTimestamp( $nTs );
		return $this->filterByCreatedAt( $oCbn->endOfWeek()->timestamp, '<=' )
					->filterByCreatedAt( $oCbn->startOfWeek()->timestamp, '>=' );
	}

	/**
	 * @param int $nTs
	 * @return $this
	 */
	public function filterByBoundary_Year( $nTs ) {
		$oCbn = ( new Carbon() )->setTimestamp( $nTs );
		return $this->filterByCreatedAt( $oCbn->startOfYear()->timestamp, '>=' )
					->filterByCreatedAt( $oCbn->endOfYear()->timestamp, '<=' );
	}

	/**
	 * @return string
	 */
	protected function getBaseQuery() {
		return "SELECT * FROM `%s` WHERE %s %s";
	}

	/**
	 * @return Handler
	 */
	public function getDbH() {
		return $this->oDbH;
	}

	/**
	 * @param Handler $oDbH
	 * @return $this
	 */
	public function setDbH( $oDbH ) {
		$this->oDbH = $oDbH;
		return $this;
	}

	/**
	 * @return bool
	 */
	public function query() {
		$mResult = Services::WpDb()->doSql( $this->buildQuery() );
		return ( $mResult === false ) ? false : $mResult > 0;
	}

	/**
	 * @return int
	 */
	public function getLimit() {
		return max( (int)$this->nLimit, 0 );
	}

	/**
	 * @return array
	 */
	public function getWheres() {
		if ( !is_array( $this->aWheres ) ) {
			$this->aWheres = [];
		}
		return $this->aWheres;
	}

	/**
	 * @return string
	 */
	public function getGroupBy() {
		return empty( $this->sGroupBy ) ? '' : sprintf( 'GROUP BY `%s`', $this->sGroupBy );
	}

	/**
	 * @return string
	 */
	public function getOrderBy() {
		return !empty( $this->sOrderBy ) ? $this->sOrderBy : 'ORDER BY `created_at` DESC';
	}

	/**
	 * @return int
	 */
	public function getPage() {
		return max( (int)$this->nPage, 1 );
	}

	/**
	 * @return bool
	 */
	public function hasLimit() {
		return $this->getLimit() > 0;
	}

	/**
	 * @return bool
	 */
	public function hasWheres() {
		return count( $this->getWheres() ) > 0;
	}

	/**
	 * @return bool
	 */
	public function isExcludeDeleted() {
		return isset( $this->bExcludeDeleted ) ? (bool)$this->bExcludeDeleted : true;
	}

	/**
	 * @return $this
	 */
	public function reset() {
		return $this->setLimit( 0 )
					->setWheres( [] )
					->setPage( 1 )
					->setOrderBy( '' );
	}

	/**
	 * @param mixed $bExcludeDeleted
	 * @return $this
	 */
	public function setIsExcludeDeleted( $bExcludeDeleted ) {
		$this->bExcludeDeleted = $bExcludeDeleted;
		return $this;
	}

	/**
	 * @param int $nLimit
	 * @return $this
	 */
	public function setLimit( $nLimit ) {
		$this->nLimit = $nLimit;
		return $this;
	}

	/**
	 * @param string $sGroupByColumn
	 * @return $this
	 */
	public function setGroupBy( $sGroupByColumn ) {
		if ( empty( $sGroupByColumn ) ) {
			$this->sGroupBy = '';
		}
		else if ( $this->getDbH()->hasColumn( $sGroupByColumn ) ) {
			$this->sGroupBy = $sGroupByColumn;
		}
		return $this;
	}

	/**
	 * @param string $sOrderByColumn
	 * @param string $sOrder
	 * @return $this
	 */
	public function setOrderBy( $sOrderByColumn, $sOrder = 'DESC' ) {
		if ( empty( $sOrderByColumn ) ) {
			$this->sOrderBy = '';
		}
		else {
			$this->sOrderBy = sprintf( 'ORDER BY `%s` %s', esc_sql( $sOrderByColumn ), esc_sql( $sOrder ) );
		}
		return $this;
	}

	/**
	 * @param int $nPage
	 * @return $this
	 */
	public function setPage( $nPage ) {
		$this->nPage = $nPage;
		return $this;
	}

	/**
	 * @param array $aWheres
	 * @return $this
	 */
	public function setWheres( $aWheres ) {
		$this->aWheres = $aWheres;
		return $this;
	}

	/**
	 * @param EntryVO $oVo
	 * @return $this
	 */
	public function setWheresFromVo( $oVo ) {
		foreach ( $oVo->getRawDataAsArray() as $sCol => $mVal ) {
			$this->addWhereEquals( $sCol, $mVal );
		}
		return $this;
	}

	/**
	 * Very basic
	 * @param string $sOp
	 * @return bool
	 */
	protected function isValidComparisonOperator( $sOp ) {
		return in_array(
			strtoupper( $sOp ),
			[ '=', '<', '>', '!=', '<>', '<=', '>=', '<=>', 'IN', 'LIKE', 'NOT LIKE' ]
		);
	}
}