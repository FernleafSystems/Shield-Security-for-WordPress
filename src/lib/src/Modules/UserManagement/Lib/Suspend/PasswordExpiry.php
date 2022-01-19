<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Suspend;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;
use FernleafSystems\Wordpress\Plugin\Shield\Users\ShieldUserMeta;
use FernleafSystems\Wordpress\Services\Services;

class PasswordExpiry extends Base {

	/**
	 * @var int
	 */
	private $nMaxPasswordAge;

	/**
	 * @return \WP_Error|\WP_User
	 */
	protected function processUser( \WP_User $user, ShieldUserMeta $meta ) {
		if ( $this->isPassExpired( $meta ) ) {

			$user = new \WP_Error(
				$this->getCon()->prefix( 'pass-expired' ),
				implode( ' ', [
					__( 'Sorry, this account is suspended because the password has expired.', 'wp-simple-firewall' ),
					__( 'Please reset your password to regain access.', 'wp-simple-firewall' ),
					sprintf( '<a href="%s">%s &rarr;</a>',
						Services::WpGeneral()->getLostPasswordUrl(),
						__( 'Reset', 'wp-simple-firewall' )
					),
				] )
			);
		}
		return $user;
	}

	/**
	 * @param ShieldUserMeta $meta
	 * @return bool
	 */
	private function isPassExpired( $meta ) {
		/** @var UserManagement\Options $opts */
		$opts = $this->getOptions();
		if ( empty( $meta->pass_started_at ) ) {
			$meta->pass_started_at = $meta->first_seen_at;
		}
		return ( Services::Request()->ts() - $meta->pass_started_at > $opts->getPassExpireTimeout() );
	}

	public function getMaxPasswordAge() :int {
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