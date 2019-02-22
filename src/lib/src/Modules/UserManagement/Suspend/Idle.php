<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Suspend;

use FernleafSystems\Wordpress\Plugin\Shield\Users\ShieldUserMeta;
use FernleafSystems\Wordpress\Services\Services;

class Idle extends Base {

	/**
	 * @var int
	 */
	private $nVerifiedExpired;

	/**
	 * @param \WP_User       $oUser
	 * @param ShieldUserMeta $oMeta
	 * @return \WP_Error|\WP_User
	 */
	protected function processUser( $oUser, $oMeta ) {
		if ( $this->isLastVerifiedAtExpired( $oMeta ) ) {
			$oUser = new \WP_Error(
				$this->getCon()->prefix( 'pass-expired' ),
				'Sorry, this account is suspended due to in-activity. Please reset your password to gain access to your account.'
			);
		}
		return $oUser;
	}

	/**
	 * @param ShieldUserMeta $oMeta
	 * @return bool
	 */
	protected function isLastVerifiedAtExpired( $oMeta ) {
		return ( Services::Request()->ts() - $oMeta->getLastVerifiedAt() > $this->getVerifiedExpires() );
	}

	/**
	 * @return int
	 */
	public function getVerifiedExpires() {
		return (int)$this->nVerifiedExpired;
	}

	/**
	 * @param int $nVerifiedExpired
	 * @return $this
	 */
	public function setVerifiedExpires( $nVerifiedExpired ) {
		$this->nVerifiedExpired = $nVerifiedExpired;
		return $this;
	}
}