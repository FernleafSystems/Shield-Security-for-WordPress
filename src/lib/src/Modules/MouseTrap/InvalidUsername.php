<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\MouseTrap;

use FernleafSystems\Wordpress\Services\Services;

class InvalidUsername extends Base {

	protected function process() {
		add_filter( 'authenticate',
			/**
			 * @param null|\WP_User|\WP_Error $oUser
			 * @param                         $sUsernameEmail
			 * @return null|\WP_User|\WP_Error
			 */
			function ( $oUser, $sUsernameEmail ) {
				if ( !empty( $sUsernameEmail ) && !$this->userExists( $sUsernameEmail ) ) {
					$this->doTransgression();
				}
				return $oUser;
			},
			5, 2 );
	}

	/**
	 * @return bool
	 */
	protected function isTransgression() {
		/** @var \ICWP_WPSF_FeatureHandler_Mousetrap $oFO */
		$oFO = $this->getMod();
		return $oFO->isTransgressionInvalidUsernames();
	}

	/**
	 * @return $this
	 */
	protected function writeAudit() {
		$this->createNewAudit(
			'wpsf',
			sprintf( _wpsf__( 'Attempted login by invalid username "%s"' ), Services::Request()->getPath() ),
			2, 'mousetrap_invaliduser'
		);
		return $this;
	}

	/**
	 * @param string $sUsernameEmail
	 * @return bool
	 */
	private function userExists( $sUsernameEmail ) {
		$oWpUsers = Services::WpUsers();
		return ( $oWpUsers->getUserByEmail( $sUsernameEmail ) instanceof \WP_User )
			   || ( $oWpUsers->getUserByUsername( $sUsernameEmail ) instanceof \WP_User );
	}
}
