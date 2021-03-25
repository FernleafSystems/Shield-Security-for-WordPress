<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Session;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\IpListSort;
use FernleafSystems\Wordpress\Services\Services;

class Select extends Base\Select {

	use Base\Traits\Select_IPTable;

	/**
	 * @return string[]
	 */
	public function getDistinctUsernames() :array {
		return $this->getDistinct_FilterAndSort( 'wp_username' );
	}

	/**
	 * @param string $ip
	 * @return $this
	 */
	public function filterByIp( string $ip ) :self {
		if ( Services::IP()->isValidIp( $ip ) ) {
			$this->addWhereEquals( 'ip', trim( $ip ) );
		}
		return $this;
	}

	/**
	 * @return EntryVO[]|\stdClass[]
	 */
	public function all() {
		return $this->selectForUserSession();
	}

	/**
	 * @param int $nExpiredBoundary
	 * @return $this
	 */
	public function filterByLoginNotExpired( $nExpiredBoundary ) {
		return $this->addWhereNewerThan( $nExpiredBoundary, 'logged_in_at' );
	}

	/**
	 * @param int $nExpiredBoundary
	 * @return $this
	 */
	public function filterByLoginNotIdleExpired( $nExpiredBoundary ) {
		return $this->addWhereNewerThan( $nExpiredBoundary, 'last_activity_at' );
	}

	public function filterByUsername( string $username ) :self {
		return $this->addWhereEquals( 'wp_username', trim( $username ) );
	}

	/**
	 * @param string $ID
	 * @param string $username
	 * @return EntryVO|null
	 */
	public function retrieveUserSession( string $ID, $username = '' ) {
		$data = $this->selectForUserSession( $ID, $username );
		return ( count( $data ) == 1 ) ? array_shift( $data ) : null;
	}

	/**
	 * @param string $ID
	 * @param string $username
	 * @return EntryVO[]
	 */
	protected function selectForUserSession( $ID = '', $username = '' ) {
		if ( !empty( $username ) ) {
			$this->addWhereEquals( 'wp_username', $username );
		}
		if ( !empty( $ID ) ) {
			$this->addWhereEquals( 'session_id', $ID );
		}

		return $this->setOrderBy( 'last_activity_at', 'DESC' )->query();
	}
}