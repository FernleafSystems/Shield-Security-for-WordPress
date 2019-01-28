<?php

require_once( dirname( dirname( __DIR__ ) ).'/lib/vendor/autoload.php' );

abstract class ICWP_WPSF_Query_BaseDelete extends ICWP_WPSF_Query_BaseQuery {

	/**
	 * @return ICWP_WPSF_Query_BaseSelect
	 */
	abstract protected function getSelector();

	/**
	 * @return bool
	 */
	public function all() {
		return $this->query();
	}

	/**
	 * @param int $nId
	 * @return bool|int
	 */
	public function deleteById( $nId ) {
		return $this->query();
	}

	/**
	 * NOTE: Does not reset() before query, so may be customized with where.
	 * @param int    $nMaxEntries
	 * @param string $sSortColumn
	 * @param bool   $bOldestFirst
	 * @return int
	 * @throws Exception
	 */
	public function deleteExcess( $nMaxEntries, $sSortColumn = 'created_at', $bOldestFirst = true ) {
		return 0;
	}

	/**
	 * @return string
	 */
	protected function getBaseQuery() {
		return "DELETE FROM `%s` WHERE %s %s";
	}

	/**
	 * Offset never applies to DELETE
	 * @return string
	 */
	protected function buildOffsetPhrase() {
		return '';
	}
}