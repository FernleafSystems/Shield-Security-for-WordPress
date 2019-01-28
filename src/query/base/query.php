<?php

require_once( dirname( dirname( __DIR__ ) ).'/lib/vendor/autoload.php' );

/**
 * @deprecated v7.0.0
 * Class ICWP_WPSF_Query_BaseQuery
 */
abstract class ICWP_WPSF_Query_BaseQuery extends ICWP_WPSF_Foundation {

	/**
	 * @var bool
	 */
	protected $bResultsAsVo;

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
	protected $sTable;

	public function __construct() {
		$this->customInit();
	}

	/**
	 * override to add custom init actions
	 */
	protected function customInit() {
	}

	/**
	 * @param string $sColumn
	 * @param string $mValue
	 * @param string $sOperator
	 * @return $this
	 */
	public function addWhere( $sColumn, $mValue, $sOperator = '=' ) {
		if ( !$this->isValidComparisonOperator( $sOperator ) ) {
			return $this; // Exception?
		}

		$mValue = esc_sql( $mValue );

		if ( strcasecmp( $sOperator, 'LIKE' ) === 0 ) {
			$mValue = sprintf( '%%%s%%', $mValue );
		}

		if ( is_string( $mValue ) ) {
			$mValue = sprintf( "'%s'", $mValue );
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
			array(
				$this->getOrderBy(),
				$this->buildLimitPhrase(),
				$this->buildOffsetPhrase(),
			)
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
			$this->getTable(),
			$this->buildWhere(),
			$this->buildExtras()
		);
	}

	/**
	 * @return string
	 */
	protected function getBaseQuery() {
		return "SELECT * FROM `%s` WHERE %s %s";
	}

	/**
	 * @return bool
	 */
	public function query() {
		return false;
	}

	/**
	 * @return int
	 */
	public function getLimit() {
		return max( (int)$this->nLimit, 0 );
	}

	/**
	 * @return string
	 */
	public function getTable() {
		return $this->sTable;
	}

	/**
	 * @return array
	 */
	public function getWheres() {
		if ( !is_array( $this->aWheres ) ) {
			$this->aWheres = array();
		}
		return $this->aWheres;
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
	 * @return bool
	 */
	public function isResultsAsVo() {
		return $this->bResultsAsVo;
	}

	/**
	 * @return $this
	 */
	public function reset() {
		return $this->setLimit( 0 )
					->setWheres( array() )
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
	 * @param bool $bResultsAsVo
	 * @return $this
	 */
	public function setResultsAsVo( $bResultsAsVo ) {
		$this->bResultsAsVo = $bResultsAsVo;
		return $this;
	}

	/**
	 * @param string $sTable
	 * @return $this
	 */
	public function setTable( $sTable ) {
		$this->sTable = $sTable;
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
	 * @param ICWP_WPSF_BaseEntryVO $oVo
	 * @return $this
	 */
	public function setWheresFromVo( $oVo ) {
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
			array( '=', '<', '>', '!=', '<>', '<=', '>=', '<=>', 'LIKE', 'NOT LIKE' )
		);
	}
}