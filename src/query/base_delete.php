<?php

if ( class_exists( 'ICWP_WPSF_Query_BaseDelete', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_query.php' );

class ICWP_WPSF_Query_BaseDelete extends ICWP_WPSF_Query_BaseQuery {

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
		return $this->reset()
					->addWhereEquals( 'id', (int)$nId )
					->setLimit( 1 )//perhaps an unnecessary precaution
					->query();
	}

	/**
	 * @param int  $nLimit
	 * @param bool $bDeleteOldestEntries
	 * @return bool
	 * @throws Exception
	 */
	public function deleteExcess( $nLimit, $bDeleteOldestEntries = true ) {
		if ( is_null( $nLimit ) ) {
			throw new Exception( 'Limit not specified for table excess delete' );
		}
		return $this->reset()
					->setOrderBy( 'created_at', $bDeleteOldestEntries ? 'ASC' : 'DESC' )
					->setLimit( $nLimit )
					->query();
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