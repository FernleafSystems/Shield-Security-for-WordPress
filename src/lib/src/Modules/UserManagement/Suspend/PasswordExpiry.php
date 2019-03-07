<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Suspend;

use FernleafSystems\Wordpress\Plugin\Shield\Users\ShieldUserMeta;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class PasswordExpiry
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Suspend
 */
class PasswordExpiry extends Base {

	/**
	 * @var int
	 */
	private $nMaxPasswordAge;

	/**
	 * @param \WP_User       $oUser
	 * @param ShieldUserMeta $oMeta
	 * @return \WP_Error|\WP_User
	 */
	protected function processUser( $oUser, $oMeta ) {
		if ( $this->isPassExpired( $oMeta ) ) {
			$oUser = new \WP_Error(
				$this->getCon()->prefix( 'pass-expired' ),
				'Sorry, this account is suspended due to expired password. Please reset your password to gain access to your account.'
			);
		}
		return $oUser;
	}

	/**
	 * @param ShieldUserMeta $oMeta
	 * @return bool
	 */
	private function isPassExpired( $oMeta ) {
		$nPassStart = (int)$oMeta->pass_started_at;
		if ( empty( $nPassStart ) ) {
			$oMeta->pass_started_at = $oMeta->getLastVerifiedAt();
		}
		return ( Services::Request()->ts() - $oMeta->pass_started_at > $this->getMaxPasswordAge() );
	}

	/**
	 * @return int
	 */
	public function getMaxPasswordAge() {
		return (int)$this->nMaxPasswordAge;
	}

	/**
	 * @param int $nMaxPasswordAge
	 * @return $this
	 */
	public function setMaxPasswordAge( $nMaxPasswordAge ) {
		$this->nMaxPasswordAge = $nMaxPasswordAge;
		return $this;
	}
}