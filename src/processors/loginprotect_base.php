<?php

if ( class_exists( 'ICWP_WPSF_Processor_LoginProtect_Base', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_wpsf.php' );

abstract class ICWP_WPSF_Processor_LoginProtect_Base extends ICWP_WPSF_Processor_BaseWpsf {

	protected function setLoginAsFailed( $sStatToIncrement ) {

		remove_filter( 'authenticate', 'wp_authenticate_username_password', 20 );  // wp-includes/user.php
		remove_filter( 'authenticate', 'wp_authenticate_email_password', 20 );  // wp-includes/user.php

		$this->doStatIncrement( $sStatToIncrement );
		$this->setIpTransgressed(); // We now black mark this IP
	}
}