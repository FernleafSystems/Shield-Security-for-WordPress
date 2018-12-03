<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;

trait CommonFilters {

	/**
	 * @param string $sIp
	 * @return $this
	 */
	public function filterByIp( $sIp ) {
		return $this->addWhereEquals( 'ip', $sIp );
	}

	/**
	 * @param string $nLastAccessAfter
	 * @return $this
	 */
	public function filterByLastAccessAfter( $nLastAccessAfter ) {
		return $this->addWhereNewerThan( $nLastAccessAfter, 'last_access_at' );
	}

	/**
	 * @param string $nLastAccessBefore
	 * @return $this
	 */
	public function filterByLastAccessBefore( $nLastAccessBefore ) {
		return $this->addWhereOlderThan( $nLastAccessBefore, 'last_access_at' );
	}

	/**
	 * @param string $sList
	 * @return $this
	 */
	public function filterByList( $sList ) {
		if ( !empty( $sList ) && is_string( $sList ) ) {
			$this->filterByLists( [ $sList ] );
		}
		return $this;
	}

	/**
	 * @param array $aLists
	 * @return $this
	 */
	public function filterByLists( $aLists ) {
		if ( !empty( $aLists ) ) {
			$this->addWhereIn( 'list', $aLists );
		}
		return $this;
	}
}