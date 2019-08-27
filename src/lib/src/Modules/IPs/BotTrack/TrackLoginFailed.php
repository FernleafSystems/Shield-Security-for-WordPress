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
			 * @param null|\WP_User|\WP_Error $oUser
			 * @param string                  $sLogin
			 * @param string                  $sPass
			 * @return null|\WP_User|\WP_Error
			 */
			function ( $oUser, $sLogin, $sPass ) {
				if ( is_wp_error( $oUser ) && !empty( $sLogin )
					 && !empty( $sPass ) && Services::WpUsers()->exists( $sLogin ) ) {
					$this->user_login = Services::Data()->validEmail( $sLogin ) ? $sLogin : sanitize_user( $sLogin );
					$this->doTransgression();

					// Adds an extra message to login failed
					$oUser->add(
						$this->getMod()->prefix( 'transgression-warning' ),
						$this->getMod()->getTextOpt( 'text_loginfailed' )
					);
				}
				return $oUser;
			},
			21, 3 ); //right after username/password check
	}

	/**
	 * @return array
	 */
	protected function getAuditData() {
		return [
			'login' => $this->user_login
		];
	}
}
