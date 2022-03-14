<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;

trait CommonFilters {

	/**
	 * @param string $ip
	 * @return $this
	 */
	public function filterByIp( $ip ) {
		return $this->addWhereEquals( 'ip', $ip );
	}

	/**
	 * @param bool $isBlocked
	 * @return $this
	 */
	public function filterByBlocked( $isBlocked ) {
		return $this->addWhere( 'blocked_at', 0, $isBlocked ? '>' : '=' );
	}

	/**
	 * @return $this
	 */
	public function filterByBlacklist() {
		return $this->filterByLists( [
			ModCon::LIST_AUTO_BLACK,
			ModCon::LIST_MANUAL_BLACK
		] );
	}

	/**
	 * @return $this
	 */
	public function filterByWhitelist() {
		return $this->filterByList( ModCon::LIST_MANUAL_WHITE );
	}

	/**
	 * @param bool $isRange
	 * @return $this
	 */
	public function filterByIsRange( bool $isRange ) {
		return $this->addWhereEquals( 'is_range', $isRange ? 1 : 0 );
	}

	/**
	 * @param string $label
	 * @return $this
	 */
	public function filterByLabel( string $label ) {
		return $this->addWhereEquals( 'label', $label );
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