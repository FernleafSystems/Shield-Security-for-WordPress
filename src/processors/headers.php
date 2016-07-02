<?php

if ( !class_exists( 'ICWP_WPSF_Processor_Headers' ) ):

	require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'base_wpsf.php' );

	class ICWP_WPSF_Processor_Headers extends ICWP_WPSF_Processor_BaseWpsf {

		/**
		 */
		public function run() {
			add_action( 'send_headers', array( $this, 'addSecurityHeaders' ) );
		}

		public function addSecurityHeaders() {
			/** @var ICWP_WPSF_FeatureHandler_Lockdown $oFO */
			$oFO = $this->getFeatureOptions();

			if ( $oFO->getOptIs( 'x_frame', 'Y' ) ) {
				header( 'x-frame-options: SAMEORIGIN' );
			}
			if ( $oFO->getOptIs( 'x_xss_protect', 'Y' ) ) {
				header( 'X-XSS-Protection: 1; mode=block' );
			}
			if ( $oFO->getOptIs( 'x_content_type', 'Y' ) ) {
				header( 'X-Content-Type-Options: nosniff' );
			}

			$aDomains = $oFO->getContentSecurityPolicyDomains();
			if ( !empty( $aDomains ) && is_array( $aDomains ) ) {
				header( sprintf( "Content-Security-Policy: default-src 'self' 'unsafe-inline' data: %s", implode( " ", $aDomains ) ) );
			}
//
//			$aDomains = array(
//				'fonts.googleapis.com',
//				'load.sumome.com',
//				'cdn.segment.com',
//				'www.googletagmanager.com',
//				'secure.gravatar.com',
//				'fonts.googleapis.com',
//				'fonts.gstatic.com',
//				'www.google-analytics.com',
//				'cdn.mxpnl.com',
//				'sumome.com',
//				'www.googletagmanager.com',
//				'api.mixpanel.com',
//				'*.kxcdn.com',
//				'*.google.com',
//				'*.reddit.com',
//				'*.bufferapp.com',
//				'*.linkedin.com',
//				'*.pinterest.com',
//				'*.yummly.com',
//				'*.facebook.com',
//			);
		}
	}

endif;