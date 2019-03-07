<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\BotTrap;

use FernleafSystems\Wordpress\Services\Services;

class FailedAuthenticate extends Base {

	protected function process() {
		add_filter( 'authenticate',
			/**
			 * @param null|\WP_User|\WP_Error $oUser
			 * @param string                  $sUsernameEmail
			 * @return null|\WP_User|\WP_Error
			 */
			function ( $oUser, $sUsernameEmail, $sPass ) {
				if ( is_wp_error( $oUser ) && !empty( $sUsernameEmail )
					 && !empty( $sPass ) && Services::WpUsers()->exists( $sUsernameEmail ) ) {
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
		/** @var \ICWP_WPSF_FeatureHandler_Bottrap $oFO */
		$oFO = $this->getMod();
		return $oFO->isTransgressionFailedLogins();
	}

	/**
	 * @return $this
	 */
	protected function writeAudit() {
		$this->createNewAudit(
			'wpsf',
			sprintf( _wpsf__( 'Attempted login failed by username "%s"' ), Services::Request()->getPath() ),
			2, 'bottrap_invaliduser'
		);
		return $this;
	}
}
