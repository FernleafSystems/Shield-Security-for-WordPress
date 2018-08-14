<?php

if ( class_exists( 'ICWP_WPSF_Query_BaseDelete', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_query.php' );

abstract class ICWP_WPSF_Query_BaseDelete extends ICWP_WPSF_Query_BaseQuery {

	/**
	 * @return ICWP_WPSF_Query_BaseCount
	 */
	abstract protected function getCounter();

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
	 * @param int  $nMaxEntries
	 * @param bool $bOldestEntriesFirst
	 * @return int
	 * @throws Exception
	 */
	public function deleteExcess( $nMaxEntries, $bOldestEntriesFirst = true ) {
		if ( is_null( $nMaxEntries ) ) {
			throw new Exception( 'Max Entries not specified for table excess delete.' );
		}

		$nEntriesDeleted = 0;

		$nToDelete = $this->getCounter()->all() - $nMaxEntries;
		if ( $nToDelete > 0 ) {
			$nEntriesDeleted = $this->reset()
									->setOrderBy( 'created_at', $bOldestEntriesFirst ? 'ASC' : 'DESC' )
									->setLimit( $nToDelete )
									->query();
		}

		return $nEntriesDeleted;
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