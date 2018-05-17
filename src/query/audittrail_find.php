<?php

if ( class_exists( 'ICWP_WPSF_Query_AuditTrail_Find', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_find.php' );

class ICWP_WPSF_Query_AuditTrail_Find extends ICWP_WPSF_Query_Base_Find {

	public function __construct() {
		$this->init();
	}

	/**
	 * @return stdClass[]|ICWP_WPSF_AuditTrailEntryVO[]
	 * @throws Exception
	 */
	public function all() {
		return $this->query_Search();
	}

	/**
	 * @return ICWP_WPSF_AuditTrailEntryVO[]|stdClass[]
	 * @throws Exception
	 */
	protected function query_Search() {

		$sQuery = "
			SELECT *
			FROM `%s`
			%s
			ORDER BY `created_at` DESC
			%s
		";
		$sQuery = sprintf(
			$sQuery,
			$this->getTable(),
			$this->buildWherePhrase(),
			$this->hasLimit() ? sprintf( 'LIMIT %s', $this->getLimit() ) : ''
		);

		$aData = $this->loadDbProcessor()
					  ->selectCustom( $sQuery, OBJECT_K );

		if ( $this->isResultsAsVo() ) {
			foreach ( $aData as $nKey => $oAudit ) {
				$aData[ $nKey ] = new ICWP_WPSF_AuditTrailEntryVO( $oAudit );
			}
		}
		return $aData;
	}

	/**
	 * @return string
	 */
	protected function buildWherePhrase() {
		$sPhrase = '';

		if ( $this->hasSearchTerm() ) {
			$sTerm = str_replace( '"', '', esc_sql( trim( $this->getTerm() ) ) );

			$sWhereTemplate = '`%s` LIKE "%%%s%%"';
			$aColumnWheres = $this->getColumns();
			foreach ( $aColumnWheres as $nKey => $sColumn ) {
				$aColumnWheres[ $nKey ] = sprintf( $sWhereTemplate, $sColumn, $sTerm );
			}
			$sPhrase = sprintf( 'WHERE %s', implode( ' OR ', $aColumnWheres ) );
		}

		return $sPhrase;
	}

	protected function init() {
		require_once( dirname( __FILE__ ).'/ICWP_WPSF_AuditTrailEntryVO.php' );
	}
}