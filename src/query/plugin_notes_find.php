<?php

if ( class_exists( 'ICWP_WPSF_Query_PluginNotes_Find', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_find.php' );

class ICWP_WPSF_Query_PluginNotes_Find extends ICWP_WPSF_Query_Base_Find {

	public function __construct() {
		$this->init();
	}

	/**
	 * @return ICWP_WPSF_NoteVO[]|stdClass[]
	 */
	public function all() {
		return $this->query_Search();
	}

	/**
	 * @return ICWP_WPSF_NoteVO[]|stdClass[]
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
			foreach ( $aData as $nKey => $oResult ) {
				$aData[ $nKey ] = new ICWP_WPSF_NoteVO( $oResult );
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

	/**
	 * @return array
	 */
	protected function getDefaultColumns() {
		return array( 'wp_username', 'note' );
	}

	protected function init() {
		require_once( dirname( __FILE__ ).'/ICWP_WPSF_NoteVO.php' );
	}
}