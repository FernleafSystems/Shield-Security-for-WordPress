<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Session;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Services\Services;

class Select extends Base\Select {

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

	/**
	 * @param int $sUsername
	 * @return $this
	 */
	public function filterByUsername( $sUsername ) {
		return $this->addWhereEquals( 'wp_username', trim( $sUsername ) );
	}

	/**
	 * @param string $sSessionId
	 * @param string $sWpUsername
	 * @return EntryVO|null
	 */
	public function retrieveUserSession( $sSessionId, $sWpUsername = '' ) {
		$aData = $this->selectForUserSession( $sSessionId, $sWpUsername );
		return ( count( $aData ) == 1 ) ? array_shift( $aData ) : null;
	}

	/**
	 * @param string $sSessionId
	 * @param string $sWpUsername
	 * @return EntryVO[]
	 */
	protected function selectForUserSession( $sSessionId = '', $sWpUsername = '' ) {
		if ( !empty( $sWpUsername ) ) {
			$this->addWhereEquals( 'wp_username', $sWpUsername );
		}
		if ( !empty( $sSessionId ) ) {
			$this->addWhereEquals( 'session_id', $sSessionId );
		}

		/** @var EntryVO[] $aRes */
		$aRes = $this->setOrderBy( 'last_activity_at', 'DESC' )->query();
		return $aRes;
	}
}