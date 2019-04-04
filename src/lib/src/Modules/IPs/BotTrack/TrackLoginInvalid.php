<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\BotTrack;

use FernleafSystems\Wordpress\Services\Services;

class TrackLoginInvalid extends Base {

	const OPT_KEY = 'track_logininvalid';

	/**
	 * @var string
	 */
	private $user_login;

	protected function process() {
		add_filter( 'authenticate',
			/**
			 * @param null|\WP_User|\WP_Error $oUser
			 * @param string                  $sLogin
			 * @return null|\WP_User|\WP_Error
			 */
			function ( $oUser, $sLogin ) {
				if ( !empty( $sLogin ) && !Services::WpUsers()->exists( $sLogin ) ) {
					$this->user_login = Services::Data()->validEmail( $sLogin ) ? $sLogin : sanitize_user( $sLogin );
					$this->doTransgression();
				}
				return $oUser;
			},
			5, 2 );
	}

	/**
	 * @return $this
	 */
	protected function getAuditMsg() {
		return sprintf( _wpsf__( 'Attempted login with invalid user "%s"' ), $this->user_login );
	}
}
