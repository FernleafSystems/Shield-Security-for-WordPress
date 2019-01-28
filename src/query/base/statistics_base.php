<?php

require_once( dirname( dirname( __DIR__ ) ).'/lib/vendor/autoload.php' );

class ICWP_WPSF_Query_Statistics_Base extends ICWP_WPSF_Query_Base {

	/**
	 * @var ICWP_WPSF_FeatureHandler_Statistics
	 */
	protected $oFO;

	/**
	 * @var int
	 */
	protected $nDateFrom;

	/**
	 * @var int
	 */
	protected $nDateTo;

	/**
	 * @var int
	 */
	protected $nQueryLimit;

	/**
	 * @var array
	 */
	protected $aStatKeys;

	/**
	 * @var bool
	 */
	protected $bSelectDeleted;

	/**
	 * @return StatisticsReportingVO[]
	 */
	protected function runQuery() {

		$sQuery = $this->buildQuery();
		$mResult = $this->loadDbProcessor()->selectCustom( $sQuery, OBJECT );

		// TODO: NOT PHP 5.2!
		if ( is_array( $mResult ) ) {
			include_once( __DIR__.'/StatisticsReportingVO.php' );
			$mResult = array_map(
				function ( $oData ) {
					return new StatisticsReportingVO( $oData );
				},
				$mResult
			);
		}
		else {
			$mResult = array();
		}
		return $mResult;
	}

	/**
	 * @param bool $bIsCount
	 * @return string
	 */
	protected function buildQuery( $bIsCount = false ) {
		$sQuery = "
				SELECT %s
					FROM `%s`
				WHERE
					`created_at` > %s AND `created_at` < %s
					AND `deleted_at` %s 0
					%s
					%s
			";

		$sStatPart = $this->buildStatKeyQuery();
		return sprintf( $sQuery,
			$bIsCount ? 'COUNT(*) AS total' : '*',
			$this->getMod()->getFullReportingTableName(),
			$this->getDateFrom(),
			$this->getDateTo(),
			$this->isSelectDeleted() ? '>' : '=',
			empty( $sStatPart ) ? $sStatPart : 'AND '.$sStatPart,
			$this->getQueryLimit()
		);
	}

	protected function deleteAllFromTo() {
		$this->loadDbProcessor()->doSql( $this->buildDeleteQuery() );
	}

	/**
	 * @return string
	 */
	protected function buildDeleteQuery() {
		$sQuery = "
				DELETE FROM `%s`
				WHERE
					`created_at` > %s AND `created_at` < %s
					%s
			";

		$sStatPart = $this->buildStatKeyQuery();
		return sprintf( $sQuery,
			$this->getMod()->getFullReportingTableName(),
			$this->getDateFrom(),
			$this->getDateTo(),
			empty( $sStatPart ) ? $sStatPart : 'AND '.$sStatPart
		);
	}

	/**
	 * @return string
	 */
	protected function buildStatKeyQuery() {

		$sQuery = '';
		if ( $this->hasStatKeys() ) {
			$aKeys = $this->getStatKeys();
			if ( count( $aKeys ) == 1 ) {
				$sQuery = sprintf( '`stat_key` = "%s"', $aKeys[ 0 ] );
			}
			else {
				$sQuery = sprintf( '`stat_key` IN ("%s")', implode( '","', $aKeys ) );
			}
		}

		return $sQuery;
	}

	/**
	 * @return int
	 */
	public function getDateFrom() {
		return isset( $this->nDateFrom ) ? (int)$this->nDateFrom : 0;
	}

	/**
	 * @return int
	 */
	public function getDateTo() {
		return isset( $this->nDateTo ) ? (int)$this->nDateTo : $this->loadRequest()->ts();
	}

	/**
	 * @return int
	 */
	public function getQueryLimit() {
		return isset( $this->nQueryLimit ) ? 'LIMIT '.$this->nQueryLimit : '';
	}

	/**
	 * @return array
	 */
	public function getStatKeys() {
		if ( !isset( $this->aStatKeys ) ) {
			$this->aStatKeys = array();
		}
		return $this->aStatKeys;
	}

	/**
	 * @return bool
	 */
	public function hasStatKeys() {
		return ( count( $this->getStatKeys() ) > 0 );
	}

	/**
	 * @return bool
	 */
	public function isSelectDeleted() {
		return isset( $this->bSelectDeleted ) ? (bool)$this->bSelectDeleted : false;
	}

	/**
	 * @param int $nDateFrom
	 * @return $this
	 */
	public function setDateFrom( $nDateFrom ) {
		$this->nDateFrom = $nDateFrom;
		return $this;
	}

	/**
	 * @param int $nDateTo
	 * @return $this
	 */
	public function setDateTo( $nDateTo ) {
		$this->nDateTo = $nDateTo;
		return $this;
	}

	/**
	 * @param int $nLimit
	 * @return $this
	 */
	public function setQueryLimit( $nLimit ) {
		$this->nQueryLimit = $nLimit;
		return $this;
	}

	/**
	 * @param string $sStatKey
	 * @return $this
	 */
	public function addStatKey( $sStatKey ) {
		$aKeys = $this->getStatKeys();
		$sStatKey = esc_sql( trim( $sStatKey ) );
		if ( !in_array( $sStatKey, $aKeys ) ) {
			$aKeys[] = $sStatKey;
		}
		return $this->setStatKeys( $aKeys );
	}

	/**
	 * @param bool $bSelectDeleted
	 * @return $this
	 */
	public function setSelectDeleted( $bSelectDeleted ) {
		$this->bSelectDeleted = $bSelectDeleted;
		return $this;
	}

	/**
	 * @param array $aKeys
	 * @return $this
	 */
	public function setStatKeys( $aKeys ) {
		if ( !is_array( $aKeys ) ) {
			$aKeys = array();
		}
		$this->aStatKeys = $aKeys;
		return $this;
	}

	/**
	 * @return ICWP_WPSF_FeatureHandler_Statistics
	 */
	protected function getMod() {
		return $this->oFO;
	}

	/**
	 * @param ICWP_WPSF_FeatureHandler_Statistics $oFeature
	 * @return $this
	 */
	public function setFeature( $oFeature ) {
		$this->oFO = $oFeature;
		return $this;
	}
}