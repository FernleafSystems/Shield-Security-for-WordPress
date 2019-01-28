<?php

require_once( dirname( dirname( __DIR__ ) ).'/lib/vendor/autoload.php' );

class ICWP_WPSF_Query_Base extends ICWP_WPSF_Foundation {

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

	/**
	 * @param string $sColumn
	 * @param string $mValue
	 * @param string $sOperator
	 * @return $this
	 */
	public function addWhere( $sColumn, $mValue, $sOperator = '=' ) {
		$aWhere = $this->getWheres();
		$aWhere[] = sprintf( '`%s`%s"%s"', esc_sql( $sColumn ), esc_sql( $mValue ), $sOperator );
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
	public function buildOffsetPhrase() {
		return $this->hasLimit() ? sprintf( 'OFFSET %s', $this->getLimit()*$this->getPage() ) : '';
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
	protected function buildQuery() {
		$sQuery = sprintf( $this->getBaseQuery(),
			$this->getTable(),
			$this->buildWhere(),
			$this->buildExtras()
		);
		return sprintf( $sQuery,
			$this->getTable(),
			$this->buildWhere(),
			$this->buildExtras()
		);
	}

	/**
	 * @return string
	 */
	protected function getBaseQuery() {
		return "
			SELECT * FROM `%s`
			WHERE %s
			%s
		";
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
		return isset( $this->sOrderBy ) ? $this->sOrderBy : 'ORDER BY `created_at` DESC';
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
	public function isResultsAsVo() {
		return $this->bResultsAsVo;
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
					->setWheres( array() )
					->setPage( 1 )
					->setOrderBy( '' );
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
		$this->sOrderBy = sprintf( 'ORDER BY `%s` %s', $sOrderByColumn, $sOrder );
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
}