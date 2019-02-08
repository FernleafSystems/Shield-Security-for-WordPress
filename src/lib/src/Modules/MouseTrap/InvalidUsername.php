<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\MouseTrap;

use FernleafSystems\Wordpress\Services\Services;

class InvalidUsername extends Base {

	protected function process() {
		add_filter( 'authenticate', array( $this, 'onAuthenticate' ), 5, 2 );
	}

	/**
	 * @param null|\WP_User|\WP_Error $oUser
	 * @param                         $sUsernameEmail
	 * @return null|\WP_User|\WP_Error
	 */
	public function onAuthenticate( $oUser, $sUsernameEmail ) {
		/** @var \ICWP_WPSF_FeatureHandler_Mousetrap $oFO */
		$oFO = $this->getMod();

		if ( !empty( $sUsernameEmail ) && !$this->userExists( $sUsernameEmail ) ) {
			if ( $oFO->isTransgression404() ) {
				$oFO->setIpTransgressed();
			}
			else {
				$oFO->setIpBlocked();
			}

			$this->createNewAudit(
				'wpsf',
				sprintf( '%s: %s', _wpsf__( 'MouseTrap' ), _wpsf__( 'Invalid username attempted login' ) ),
				2, 'mousetrap_invalidusername'
			);
		}

		return $oUser;
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
