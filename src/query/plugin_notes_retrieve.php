<?php

if ( class_exists( 'ICWP_WPSF_Query_PluginNotes_Retrieve', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base.php' );

class ICWP_WPSF_Query_PluginNotes_Retrieve extends ICWP_WPSF_Query_Base {

	public function __construct() {
		$this->init();
	}

	/**
	 * @return ICWP_WPSF_NoteVO[]|stdClass[]
	 */
	public function all() {
		return $this->query_retrieve();
	}

	/**
	 * @param int $nId
	 * @return ICWP_WPSF_NoteVO[]|stdClass[]
	 */
	public function retrieveById( $nId ) {
		return $this->query_retrieve( $nId );
	}

	/**
	 * @param int $nId
	 * @return ICWP_WPSF_NoteVO[]|stdClass[]
	 */
	protected function query_retrieve( $nId = null ) {
		$sQuery = "
			SELECT *
			FROM `%s`
			WHERE
				`deleted_at` = 0
				%s
			ORDER BY `created_at` DESC
			%s
		";
		$sQuery = sprintf(
			$sQuery,
			$this->getTable(),
			is_null( $nId ) ? '' : "AND `id` = ".esc_sql( (int)$nId ),
			$this->hasLimit() ? sprintf( 'LIMIT %s', $this->getLimit() ) : ''
		);

		$aData = $this->loadDbProcessor()
					  ->selectCustom( $sQuery, OBJECT_K );

		if ( $this->isResultsAsVo() ) {
			foreach ( $aData as $nKey => $oResult ) {
				$aData[ $nKey ] = new ICWP_WPSF_NoteVO( $oResult );
			}
		}
		return $aData;
	}

	protected function init() {
		require_once( dirname( __FILE__ ).'/ICWP_WPSF_NoteVO.php' );
	}
}