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
	 * @return array[]|ICWP_WPSF_AuditTrailEntryVO[]
	 * @throws Exception
	 */
	public function all() {
		return $this->query_Search( $this->getTerm() );
	}

	/**
	 * @param string $sTerm
	 * @return ICWP_WPSF_AuditTrailEntryVO[]|array[]
	 * @throws Exception
	 */
	protected function query_Search( $sTerm ) {

		$sTerm = str_replace( '"', '', esc_sql( trim( $sTerm ) ) );
		if ( empty( $sTerm ) ) {
			throw new Exception( 'Search term cannot be empty for delete request.' );
		}

		$sWhereTemplate = '`%s` LIKE "%%%s%%"';
		$aColumnWheres = $this->getColumns();
		foreach ( $aColumnWheres as $nKey => $sColumn ) {
			$aColumnWheres[ $nKey ] = sprintf( $sWhereTemplate, $sColumn, $sTerm );
		}

		$sQuery = "
			SELECT *
			FROM `%s`
			WHERE %s
		";
		$sQuery = sprintf(
			$sQuery,
			$this->getTable(),
			implode( ' OR ', $aColumnWheres )
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

	protected function init() {
		require_once( dirname( __FILE__ ).'/ICWP_WPSF_AuditTrailEntryVO.php' );
	}
}