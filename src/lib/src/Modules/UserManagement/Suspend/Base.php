<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Suspend;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Users\ShieldUserMeta;
use FernleafSystems\Wordpress\Services\Services;

class Base {

	use PluginControllerConsumer;

	/**
	 * @var int
	 */
	private $nVerifiedExpired;

	public function run() {
		add_filter( 'authenticate', array( $this, 'checkUser' ), 1000, 1 );
	}

	/**
	 * Should be a filter added to WordPress's "authenticate" filter, but before WordPress performs
	 * it's own authentication (theirs is priority 30, so we could go in at around 20).
	 * @param null|\WP_User|\WP_Error $oUserOrError
	 * @return \WP_User|\WP_Error
	 */
	public function checkUser( $oUserOrError ) {
		if ( $oUserOrError instanceof \WP_User ) {
			$oMeta = $this->getCon()->getUserMeta( $oUserOrError );
			if ( !$this->isLastVerifiedAtExpired( $oMeta ) ) {
				$oUserOrError = $this->processUser( $oUserOrError, $oMeta );
			}
		}
		return $oUserOrError;
	}

	/**
	 * @param ShieldUserMeta $oMeta
	 * @return bool
	 */
	protected function isLastVerifiedAtExpired( $oMeta ) {
		$nNow = Services::Request()->ts();
		$nLastVerified = (int)$oMeta->last_verified_at;
		if ( $nLastVerified < 1 ) {
			$nLastVerified = (int)max( $oMeta->last_login_at, $oMeta->pass_started_at );
			if ( $nLastVerified < 1 ) {
				$nLastVerified = $nNow;
			}
			$oMeta->last_verified_at = $nLastVerified;
		}
		return ( $nNow - $oMeta->last_verified_at > $this->getVerifiedExpires() );
	}

	/**
	 * Test the User and its Meta and if it fails return \WP_Error
	 * @param \WP_User       $oUser
	 * @param ShieldUserMeta $oMeta
	 * @return \WP_Error|\WP_User
	 */
	protected function processUser( $oUser, $oMeta ) {
		return $oUser;
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