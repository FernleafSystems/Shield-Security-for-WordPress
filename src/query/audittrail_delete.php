<?php

if ( class_exists( 'ICWP_WPSF_Query_AuditTrail_Delete', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_find.php' );

class ICWP_WPSF_Query_AuditTrail_Delete extends ICWP_WPSF_Query_Base_Find {

	/**
	 * @return bool|int
	 * @throws Exception
	 */
	public function all() {
		return $this->query_Delete( $this->getTerm() );
	}

	/**
	 * @param string $sTerm
	 * @return bool|int
	 * @throws Exception
	 */
	protected function query_Delete( $sTerm ) {

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
			DELETE FROM `%s`
			WHERE %s
		";
		$sQuery = sprintf(
			$sQuery,
			$this->getTable(),
			implode( ' OR ', $aColumnWheres )
		);

		return $this->loadDbProcessor()->doSql( $sQuery );
	}
}