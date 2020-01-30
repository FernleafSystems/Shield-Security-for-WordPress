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
	 * @param bool $bIsBlocked
	 * @return $this
	 */
	public function filterByBlocked( $bIsBlocked ) {
		return $this->addWhere( 'blocked_at', 0, $bIsBlocked ? '>' : '=' );
	}

	/**
	 * @return $this
	 */
	public function filterByBlacklist() {
		return $this->filterByLists( [
			\ICWP_WPSF_FeatureHandler_Ips::LIST_AUTO_BLACK,
			\ICWP_WPSF_FeatureHandler_Ips::LIST_MANUAL_BLACK
		] );
	}

	/**
	 * @return $this
	 */
	public function filterByWhitelist() {
		return $this->filterByList( \ICWP_WPSF_FeatureHandler_Ips::LIST_MANUAL_WHITE );
	}

	/**
	 * @param bool $bIsRange
	 * @return $this
	 */
	public function filterByIsRange( $bIsRange ) {
		return $this->addWhereEquals( 'is_range', $bIsRange ? 1 : 0 );
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