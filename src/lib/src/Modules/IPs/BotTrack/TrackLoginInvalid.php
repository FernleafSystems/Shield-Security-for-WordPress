<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\BotTrack;

use FernleafSystems\Wordpress\Services\Services;

class TrackLoginInvalid extends Base {

	public const OPT_KEY = 'track_logininvalid';

	/**
	 * @var string
	 */
	private $user_login;

	protected function process() {
		add_filter( 'authenticate',
			/**
			 * @param null|\WP_User|\WP_Error $user
			 * @param string                  $login
			 * @param string                  $pass
			 * @return null|\WP_User|\WP_Error
			 */
			function ( $user, $login, $pass ) {
				if ( Services::Request()->isPost() && is_wp_error( $user ) && !empty( $pass )
					 && ( empty( $login ) || !Services::WpUsers()->exists( $login ) ) ) {

					if ( empty( $login ) ) {
						$this->user_login = 'empty username';
					}
					else {
						$this->user_login = Services::Data()->validEmail( $login ) ? $login : sanitize_user( $login );
					}
					$this->doTransgression();
				}
				return $user;
			},
			21, 3 );
	}

	protected function getAuditData() :array {
		return [
			'user_login' => $this->user_login
		];
	}
}
