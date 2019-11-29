<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Services\Services;

class Select extends Base\Select {

	/**
	 * @return string[]
	 */
	public function getDistinctEvents() {
		return $this->getDistinct_FilterAndSort( 'event' );
	}

	/**
	 * @return string[]
	 */
	public function getDistinctIps() {
		return $this->getDistinct_FilterAndSort( 'ip' );
	}

	/**
	 * @return string[]
	 */
	public function getDistinctUsernames() {
		return $this->getDistinct_FilterAndSort( 'wp_username' );
	}

	/**
	 * @param string $sEvent
	 * @return $this
	 */
	public function filterByEvent( $sEvent ) {
		if ( !empty( $sEvent ) && strtolower( $sEvent ) != 'all' ) {
			$this->addWhereEquals( 'event', $sEvent );
		}
		return $this;
	}

	/**
	 * @param string $sIp
	 * @return $this
	 */
	public function filterByIp( $sIp ) {
		if ( Services::IP()->isValidIp( $sIp ) ) {
			$this->addWhereEquals( 'ip', trim( $sIp ) );
		}
		return $this;
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