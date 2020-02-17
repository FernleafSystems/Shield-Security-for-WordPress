<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @deprecated 8.6.0
 */
class ICWP_WPSF_Processor_LoginProtect_Intent extends Shield\Modules\BaseShield\ShieldProcessor {

	/**
	 * @var ICWP_WPSF_Processor_LoginProtect_Track
	 */
	private $oLoginTrack;

	/**
	 * @var bool
	 */
	private $bLoginIntentProcessed;

	/**
	 */
	public function run() {
	}

	/**
	 * @param int $nUserId
	 */
	public function onWcSocialLogin( $nUserId ) {
	}

	protected function setupLoginIntent() {
	}

	/**
	 * @param WP_User|WP_Error $oUser
	 */
	protected function initLoginIntent( $oUser ) {}

	/**
	 * hooked to 'init' and only run if a user is logged-in (not on the login request)
	 */
	private function processLoginIntent() {}

	/**
	 * Use this ONLY when the login intent has been successfully verified.
	 * @return $this
	 */
	private function removeLoginIntent() {
		return $this->setLoginIntentExpiresAt( 0 );
	}

	/**
	 * @param int $nExpirationTime
	 * @return $this
	 */
	protected function setLoginIntentExpiresAt( $nExpirationTime ) {
		return $this;
	}

	/**
	 * @return int
	 */
	protected function getLoginIntentExpiresAt() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getMod();
		return $oFO->hasSession() ? $oFO->getSession()->login_intent_expires_at : 0;
	}

	/**
	 * @return bool
	 */
	protected function hasLoginIntent() {
		return $this->getLoginIntentExpiresAt() > 0;
	}

	/**
	 * @return bool
	 */
	protected function hasValidLoginIntent() {
		return $this->hasLoginIntent() && ( $this->getLoginIntentExpiresAt() > Services::Request()->ts() );
	}

	/**
	 */
	private function printLoginIntentForm() {}
	/**
	 */
	public function onWpLogout() {
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_Track
	 */
	public function getLoginTrack() {
		return $this->oLoginTrack;
	}

	/**
	 * assume that a user is logged in.
	 * @return bool
	 */
	private function isLoginIntentValid() {
		return false;
	}

	/**
	 * @return bool
	 */
	public function isLoginIntentProcessed() {
		return true;
	}

	/**
	 * @return $this
	 */
	public function setLoginIntentProcessed() {
		return $this;
	}

	/**
	 * @param ICWP_WPSF_Processor_LoginProtect_Track $oLoginTrack
	 * @return $this
	 */
	public function setLoginTrack( $oLoginTrack ) {
		return $this;
	}
}