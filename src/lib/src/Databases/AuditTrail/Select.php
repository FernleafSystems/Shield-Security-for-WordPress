<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Services\Services;

class Select extends Base\Select {

	use Base\Traits\Select_IPTable;

	/**
	 * @return string[]
	 */
	public function getDistinctEvents() {
		return $this->getDistinct_FilterAndSort( 'event' );
	}

	/**
	 * @return string[]
	 */
	public function getDistinctUsernames() {
		return $this->getDistinct_FilterAndSort( 'wp_username' );
	}

	/**
	 * @param string $event
	 * @return $this
	 */
	public function filterByEvent( $event ) {
		if ( !empty( $event ) && strtolower( $event ) != 'all' ) {
			$this->addWhereEquals( 'event', $event );
		}
		return $this;
	}

	/**
	 * @param string $ip
	 * @return $this
	 */
	public function filterByIp( $ip ) {
		if ( Services::IP()->isValidIp( $ip ) ) {
			$this->addWhereEquals( 'ip', trim( $ip ) );
		}
		return $this;
	}

	/**
	 * @param int $id
	 * @return $this
	 */
	public function filterByRequestID( int $id ) {
		return $this->addWhereEquals( 'rid', $id );
	}

	/**
	 * @param string $sIp
	 * @return $this
	 */
	public function filterByNotIp( $sIp ) {
		if ( Services::IP()->isValidIp( $sIp ) ) {
			$this->addWhere( 'ip', trim( $sIp ), '!=' );
		}
		return $this;
	}

	/**
	 * @param bool $bIsLoggedIn - true is logged-in, false is not logged-in
	 * @return $this
	 */
	public function filterByIsLoggedIn( $bIsLoggedIn ) {
		if ( $bIsLoggedIn ) {
			$this->addWhere( 'wp_username', '', '!=' )
				 ->addWhere( 'wp_username', 'WP Cron', '!=' ); // special case
		}
		else {
			$this->addWhereEquals( 'wp_username', '' );
		}
		return $this;
	}

	/**
	 * @param int $sUsername
	 * @return $this
	 */
	public function filterByUsername( $sUsername ) {
		return $this->addWhereEquals( 'wp_username', trim( $sUsername ) );
	}
}