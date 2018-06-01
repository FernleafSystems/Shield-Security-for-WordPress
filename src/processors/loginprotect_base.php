<?php

if ( class_exists( 'ICWP_WPSF_Processor_LoginProtect_Base', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_wpsf.php' );

abstract class ICWP_WPSF_Processor_LoginProtect_Base extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeature();

		// We give it a priority of 10 so that we can jump in before WordPress does its own validation.
		add_filter( 'authenticate', array( $this, 'checkReqWpLogin' ), 10, 1 );

		// apply to user registrations if set to do so.
		if ( $oFO->getIsCheckingUserRegistrations() ) {
			add_filter( 'registration_errors', array( $this, 'checkReqWpRegistrationErrors' ), 10, 2 );
		}
	}

	/**
	 * Should be a filter added to WordPress's "authenticate" filter, but before WordPress performs
	 * it's own authentication (theirs is priority 30, so we could go in at around 20).
	 * @param null|WP_User|WP_Error $oUserOrError
	 * @return WP_User|WP_Error
	 */
	public function checkReqWpLogin( $oUserOrError ) {
		return $oUserOrError;
	}

	/**
	 * @param WP_Error $oWpError
	 * @return WP_Error
	 */
	public function checkReqWpRegistrationErrors( $oWpError ) {
		return $oWpError;
	}

	protected function setLoginAsFailed( $sStatToIncrement ) {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeature();
		$oFO->setOptInsightsAt( 'last_login_block_at' );

		remove_filter( 'authenticate', 'wp_authenticate_username_password', 20 );  // wp-includes/user.php
		remove_filter( 'authenticate', 'wp_authenticate_email_password', 20 );  // wp-includes/user.php

		$this->doStatIncrement( $sStatToIncrement );
		$this->setIpTransgressed(); // We now black mark this IP
	}
}