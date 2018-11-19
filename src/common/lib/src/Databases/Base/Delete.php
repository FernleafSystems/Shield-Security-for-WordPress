<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Delete extends BaseQuery {

	/**
	 * @return bool
	 */
	public function all() {
		return $this->query();
	}

	/**
	 * @param int $nId
	 * @return bool
	 */
	public function deleteById( $nId ) {
		return $this->reset()
					->addWhereEquals( 'id', (int)$nId )
					->setLimit( 1 )//perhaps an unnecessary precaution
					->query();
	}

	/**
	 * @param EntryVO $oEntry
	 * @return bool
	 */
	public function deleteEntry( $oEntry ) {
		return $this->deleteById( $oEntry->id );
	}

	/**
	 * NOTE: Does not reset() before query, so may be customized with where.
	 * @param int    $nMaxEntries
	 * @param string $sSortColumn
	 * @param bool   $bOldestFirst
	 * @return int
	 * @throws \Exception
	 */
	public function deleteExcess( $nMaxEntries, $sSortColumn = 'created_at', $bOldestFirst = true ) {
		if ( is_null( $nMaxEntries ) ) {
			throw new \Exception( 'Max Entries not specified for table excess delete.' );
		}

		$nEntriesDeleted = 0;

		// The same WHEREs should apply
		$nTotal = $this->getDbH()
					   ->getQuerySelector()
					   ->setWheres( $this->getWheres() )
					   ->count();
		$nToDelete = $nTotal - $nMaxEntries;

		if ( $nToDelete > 0 ) {
			$nEntriesDeleted = $this->setOrderBy( $sSortColumn, $bOldestFirst ? 'ASC' : 'DESC' )
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