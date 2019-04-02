<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\BotTrap;

use FernleafSystems\Wordpress\Services\Services;

class FailedAuthenticate extends Base {

	/**
	 * @var string
	 */
	private $user_login;

	protected function process() {
		add_filter( 'authenticate',
			/**
			 * @param null|\WP_User|\WP_Error $oUser
			 * @param string                  $sUsernameEmail
			 * @param string                  $sPass
			 * @return null|\WP_User|\WP_Error
			 */
			function ( $oUser, $sLogin, $sPass ) {
				if ( is_wp_error( $oUser ) && !empty( $sLogin )
					 && !empty( $sPass ) && Services::WpUsers()->exists( $sLogin ) ) {
					$this->user_login = Services::Data()->validEmail( $sLogin ) ? $sLogin : sanitize_user( $sLogin );
					$this->doTransgression();
				}
				return $oUser;
			},
			21, 3 ); //right after username/password check
	}

	/**
	 * @return bool
	 */
	protected function isTransgression() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oFO */
		$oFO = $this->getMod();
		return $oFO->isTransgressionFailedLogins();
	}

	/**
	 * @return $this
	 */
	protected function writeAudit() {
		$this->createNewAudit(
			'wpsf',
			sprintf( _wpsf__( 'Attempted login failed by username "%s"' ), $this->user_login ),
			2, 'bottrap_invaliduser'
		);
		return $this;
	}
}
