<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Suspend;

use FernleafSystems\Wordpress\Plugin\Shield\Users\ShieldUserMeta;
use FernleafSystems\Wordpress\Services\Services;

class Idle extends Base {

	/**
	 * @var int
	 */
	private $nMaxIdleTime;

	/**
	 * @param \WP_User       $oUser
	 * @param ShieldUserMeta $oMeta
	 * @return \WP_Error|\WP_User
	 */
	protected function processUser( $oUser, $oMeta ) {
		if ( Services::Request()->ts() - $oMeta->last_login_at > $this->getMaxPasswordAge() ) {
			$oUser = new \WP_Error(
				$this->getCon()->prefix( 'pass-expired' ),
				'Sorry,this account is suspended. Please reset your password to gain access to your account.'
			);
		}
		return $oUser;
	}

	/**
	 * @return int
	 */
	public function getMaxIdleTime() {
		return (int)$this->nMaxIdleTime;
	}

	/**
	 * @param int $nMaxPasswordAge
	 * @return $this
	 */
	public function setMaxIdleTime( $nMaxPasswordAge ) {
		$this->nMaxIdleTime = $nMaxPasswordAge;
		return $this;
	}
}