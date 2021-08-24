<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\BotTrack;

use FernleafSystems\Wordpress\Services\Services;

class TrackLoginFailed extends Base {

	const OPT_KEY = 'track_loginfailed';

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
				if ( is_wp_error( $user ) && !empty( $login )
					 && !empty( $pass ) && Services::WpUsers()->exists( $login ) ) {
					$this->user_login = Services::Data()->validEmail( $login ) ? $login : sanitize_user( $login );
					$this->doTransgression();

					// Adds an extra message to login failed
					$user->add(
						$this->getCon()->prefix( 'transgression-warning' ),
						$this->getMod()->getTextOpt( 'text_loginfailed' )
					);
				}
				return $user;
			},
			21, 3 ); //right after username/password check
	}

	protected function getAuditData() :array {
		return [
			'user' => $this->user_login
		];
	}
}
