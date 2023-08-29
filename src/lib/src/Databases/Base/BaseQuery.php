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
	 * @var array
	 */
	protected $rawWheres;

	protected $includeSoftDeleted;

	/**
	 * @var int
	 */
	protected $nLimit = 0;

	/**
	 * @var int
	 */
	protected $nPage;

	/**
	 * @var array
	 */
	protected $aOrderBys;

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
	 * @param string $columnLeft
	 * @param string $columnRight
	 * @param string $operator
	 */
	public function addWhereCompareColumns( string $columnLeft, string $columnRight, string $operator = '=' ) {
		return $this->addRawWhere( [ $columnLeft, $operator, '`'.$columnRight.'`' ] );
	}

	/**
	 * @param string       $column
	 * @param string|array $value
	 * @param string       $operator
	 * @return $this
	 */
	public function addWhere( $column, $value, $operator = '=' ) {
		if ( !$this->isValidComparisonOperator( $operator ) ) {
			return $this; // Exception?
		}
		$schema = $this->getDbH()->getTableSchema();
		if ( !$schema->hasColumn( $column ) ) {
			return $this; // Exception?
		}

		if ( \is_array( $value ) ) {
			$value = \array_map( 'esc_sql', $value );
			$value = "('".\implode( "','", $value )."')";
		}
		else {
			if ( \strtoupper( $operator ) === 'LIKE' ) {
				$value = sprintf( '%%%s%%', $value );
			}
			if ( !\is_int( $value ) ) {
				$value = sprintf( "'%s'", esc_sql( $value ) );
			}
		}

		$rawWheres = $this->getRawWheres();
		$rawWheres[] = [
			$column,
			$operator,
			$value
		];

		return $this->setRawWheres( $rawWheres );
	}

	/**
	 * @param array $where
	 * @return $this
	 */
	public function addRawWhere( array $where ) {
		$rawWheres = $this->getRawWheres();
		$rawWheres[] = $where;
		return $this->setRawWheres( $rawWheres );
	}

	/**
	 * @param string $column
	 * @param mixed  $mValue
	 * @return $this
	 */
	public function addWhereEquals( string $column, $mValue ) {
		return $this->addWhere( $column, $mValue );
	}

	/**
	 * @param string $column
	 * @param array  $values
	 * @return $this
	 */
	public function addWhereIn( string $column, $values ) {
		if ( !empty( $values ) && \is_array( $values ) ) {
			$this->addWhere( $column, $values, 'IN' );
		}
		return $this;
	}

	/**
	 * @param string $column
	 * @param array  $values
	 * @return $this
	 */
	public function addWhereNotIn( string $column, array $values ) {
		if ( !empty( $values ) ) {
			$this->addWhere( $column, $values, 'NOT IN' );
		}
		return $this;
	}

	/**
	 * @param string $column
	 * @param string $like
	 * @param string $left
	 * @param string $right
	 * @return $this
	 */
	public function addWhereLike( string $column, $like, $left = '%', $right = '%' ) {
		return $this->addWhere( $column, $left.$like.$right, 'LIKE' );
	}

	/**
	 * @param int    $nNewerThanTimeStamp
	 * @param string $column
	 * @return $this
	 */
	public function addWhereNewerThan( $nNewerThanTimeStamp, $column = 'created_at' ) {
		return $this->addWhere( $column, $nNewerThanTimeStamp, '>' );
	}

	/**
	 * @param int    $nOlderThanTimeStamp
	 * @param string $column
	 * @return $this
	 */
	public function addWhereOlderThan( $nOlderThanTimeStamp, $column = 'created_at' ) {
		return $this->addWhere( $column, $nOlderThanTimeStamp, '<' );
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
		$aExtras = \array_filter(
			[
				$this->getGroupBy(),
				$this->buildOrderBy(),
				$this->buildLimitPhrase(),
				$this->buildOffsetPhrase(),
			]
		);
		return \implode( "\n", $aExtras );
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
		return $this->setRawWheres( [] );
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
		$wheres = $this->getRawWheres();
		if ( !$this->isIncludeSoftDeletedRows() ) {
			$wheres[] = [ 'deleted_at', '=', 0 ];
		}
		$wheres = \array_map( function ( array $where ) {
			return $this->rawWhereToString( $where );
		}, $wheres );
		return \implode( ' AND ', $wheres );
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
	 * @param int    $ts
	 * @param string $comparisonOp
	 * @return $this
	 */
	public function filterByCreatedAt( $ts, $comparisonOp ) {
		if ( !preg_match( '#[^=<>]#', $comparisonOp ) && is_numeric( $ts ) ) {
			$this->addWhere( 'created_at', (int)$ts, $comparisonOp );
		}
		return $this;
	}

	/**
	 * @param int $startTS
	 * @param int $endTS
	 * @return $this
	 */
	public function filterByBoundary( $startTS, $endTS ) {
		return $this->filterByCreatedAt( $endTS, '<=' )
					->filterByCreatedAt( $startTS, '>=' );
	}

	/**
	 * @param int $ts
	 * @return $this
	 */
	public function filterByBoundary_Day( $ts ) {
		$c = ( new Carbon() )->setTimestamp( $ts );
		return $this->filterByBoundary( $c->startOfDay()->timestamp, $c->endOfDay()->timestamp );
	}

	/**
	 * @param int $nTs
	 * @return $this
	 */
	public function filterByBoundary_Hour( $nTs ) {
		$oCbn = ( new Carbon() )->setTimestamp( $nTs );
		return $this->filterByBoundary( $oCbn->startOfHour()->timestamp, $oCbn->endOfHour()->timestamp );
	}

	/**
	 * @param int $nTs
	 * @return $this
	 */
	public function filterByBoundary_Month( $nTs ) {
		$oCbn = ( new Carbon() )->setTimestamp( $nTs );
		return $this->filterByBoundary( $oCbn->startOfMonth()->timestamp, $oCbn->endOfMonth()->timestamp );
	}

	/**
	 * @param int $nTs
	 * @return $this
	 */
	public function filterByBoundary_Week( $nTs ) {
		$oCbn = ( new Carbon() )->setTimestamp( $nTs );
		return $this->filterByBoundary( $oCbn->startOfWeek()->timestamp, $oCbn->endOfWeek()->timestamp );
	}

	/**
	 * @param int $nTs
	 * @return $this
	 */
	public function filterByBoundary_Year( $nTs ) {
		$oCbn = ( new Carbon() )->setTimestamp( $nTs );
		return $this->filterByBoundary( $oCbn->startOfYear()->timestamp, $oCbn->endOfYear()->timestamp );
	}

	protected function getBaseQuery() :string {
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
		return \max( (int)$this->nLimit, 0 );
	}

	public function getWheres() :array {
		return \is_array( $this->aWheres ) ? $this->aWheres : [];
	}

	public function getRawWheres() :array {
		return \is_array( $this->rawWheres ) ? $this->rawWheres : [];
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
	protected function buildOrderBy() {
		$sOrder = '';
		if ( !\is_array( $this->aOrderBys ) ) {
			// Defaults to created_at if aOrderBys is untouched. Set to empty array for no order
			$this->aOrderBys = [ 'created_at' => 'DESC' ];
		}
		if ( !empty( $this->aOrderBys ) ) {
			$aOrders = [];
			foreach ( $this->aOrderBys as $sCol => $sOrder ) {
				$aOrders[] = sprintf( '`%s` %s', esc_sql( $sCol ), esc_sql( $sOrder ) );
			}
			$sOrder = sprintf( 'ORDER BY %s', \implode( ', ', $aOrders ) );
		}
		return $sOrder;
	}

	/**
	 * @return int
	 */
	public function getPage() {
		return \max( (int)$this->nPage, 1 );
	}

	/**
	 * @return bool
	 */
	public function hasLimit() {
		return $this->getLimit() > 0;
	}

	public function hasWheres() :bool {
		return \count( $this->getWheres() ) > 0;
	}

	public function isIncludeSoftDeletedRows() :bool {
		return $this->includeSoftDeleted ?? false;
	}

	protected function rawWhereToString( array $rawWhere ) :string {
		return \vsprintf( '`%s` %s %s', $rawWhere );
	}

	/**
	 * @return $this
	 */
	public function reset() {
		return $this->setLimit( 0 )
					->setRawWheres( [] )
					->setPage( 1 )
					->setOrderBy( '' );
	}

	/**
	 * @param bool $includeSoftDeleted
	 * @return $this
	 */
	public function setIncludeSoftDeleted( bool $includeSoftDeleted ) {
		$this->includeSoftDeleted = $includeSoftDeleted;
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
		elseif ( $this->getDbH()->getTableSchema()->hasColumn( $sGroupByColumn ) ) {
			$this->sGroupBy = $sGroupByColumn;
		}
		return $this;
	}

	/**
	 * @param string $orderByColumn
	 * @param string $order
	 * @param bool   $replace
	 * @return $this
	 */
	public function setOrderBy( $orderByColumn, $order = 'DESC', $replace = false ) {
		if ( empty( $orderByColumn ) ) {
			$this->aOrderBys = $orderByColumn;
		}
		else {
			if ( !\is_array( $this->aOrderBys ) || $replace ) {
				$this->aOrderBys = [];
			}
			$this->aOrderBys[ $orderByColumn ] = $order;
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
	 * @param array[] $wheres
	 * @return $this
	 */
	public function setRawWheres( array $wheres ) {
		$this->rawWheres = $wheres;
		return $this->setWheres(
			\array_map( function ( array $where ) {
				return $this->rawWhereToString( $where );
			}, $this->rawWheres )
		);
	}

	/**
	 * @param array $wheres
	 * @return $this
	 */
	public function setWheres( array $wheres ) {
		$this->aWheres = $wheres;
		return $this;
	}

	/**
	 * @param EntryVO $VO
	 * @return $this
	 */
	public function setWheresFromVo( $VO ) {
		foreach ( $VO->getRawData() as $col => $mVal ) {
			$this->addWhereEquals( $col, $mVal );
		}
		return $this;
	}

	/**
	 * Very basic
	 * @param string $op
	 * @return bool
	 */
	protected function isValidComparisonOperator( $op ) {
		return \in_array(
			strtoupper( $op ),
			[ '=', '<', '>', '!=', '<>', '<=', '>=', '<=>', 'IN', 'NOT IN', 'LIKE', 'NOT LIKE' ]
		);
	}
}