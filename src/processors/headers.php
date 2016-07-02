<?php

if ( !class_exists( 'ICWP_WPSF_Processor_Headers' ) ):

	require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'base_wpsf.php' );

	class ICWP_WPSF_Processor_Headers extends ICWP_WPSF_Processor_BaseWpsf {

		/**
		 */
		public function run() {
			add_action( 'send_headers', array( $this, 'addSecurityHeaders' ) );
		}

		protected function setXFrameHeader() {
			$sXFrame = $this->getOption( 'x_frame' );
			switch ( $sXFrame ) {
				case 'on_sameorigin':
					$sXFrameOption = 'SAMEORIGIN';
					break;
				case 'on_deny':
					$sXFrameOption = 'DENY';
					break;
				default:
					$sXFrameOption = '';
					break;
			}
			if ( !empty( $sXFrameOption ) ) {
				header( sprintf( 'x-frame-options: %s', $sXFrameOption ) );
			}
		}

		protected function setXssProtectionHeader() {
			if ( $this->getIsOption( 'x_xss_protect', 'Y' ) ) {
				header( 'X-XSS-Protection: 1; mode=block' );
			}
		}

		protected function setContentTypeOptionHeader() {
			if ( $this->getIsOption( 'x_content_type', 'Y' ) ) {
				header( 'X-Content-Type-Options: nosniff' );
			}
		}

		protected function setContentSecurityPolicyHeader() {
			/** @var ICWP_WPSF_FeatureHandler_Lockdown $oFO */
			$oFO = $this->getFeatureOptions();
			if ( !$oFO->getOptIs( 'enable_x_content_security_policy', 'Y' ) ) {
				return;
			}

			$sTemplate = 'Content-Security-Policy: default-src %s;';

			$aDefaultSrcDirectives = array( "'self'" );

			if ( $oFO->getOptIs( 'xcsp_data', 'Y' ) ) {
				$aDefaultSrcDirectives[] = "data:";
			}
			if ( $oFO->getOptIs( 'xcsp_inline', 'Y' ) ) {
				$aDefaultSrcDirectives[] = "'unsafe-inline'";
			}
			if ( $oFO->getOptIs( 'xcsp_eval', 'Y' ) ) {
				$aDefaultSrcDirectives[] = "'unsafe-eval'";
			}
			if ( $oFO->getOptIs( 'xcsp_https', 'Y' ) ) {
				$aDefaultSrcDirectives[] = "https:";
			}

			$aDomains = $oFO->getOpt( 'xcsp_hosts', 'Y' );
			if ( empty( $aDomains ) || !is_array( $aDomains ) ) {
				$sDomainsList = '*';
			}
			else {
				$sDomainsList = implode( " ", $aDomains );
			}
			$aDefaultSrcDirectives[] = $sDomainsList;

			header( sprintf( $sTemplate, implode( " ", $aDefaultSrcDirectives ) ) );
		}

		public function addSecurityHeaders() {
			/** @var ICWP_WPSF_FeatureHandler_Lockdown $oFO */
			$oFO = $this->getFeatureOptions();

			$this->setXFrameHeader();
			$this->setXssProtectionHeader();
			$this->setContentTypeOptionHeader();

			if ( $oFO->getOptIs( 'enable_x_content_security_policy', 'Y' ) ) {
				$this->setContentSecurityPolicyHeader();
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