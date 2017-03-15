<?php

if ( !class_exists( 'ICWP_WPSF_FeatureHandler_Email', false ) ):

	require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'base_wpsf.php' );

	class ICWP_WPSF_FeatureHandler_Email extends ICWP_WPSF_FeatureHandler_BaseWpsf {

		protected function doPostConstruction() {
			// add filters to email sending (for now only Mandrill)
//			add_filter( 'mandrill_payload', array( $this, 'customiseMandrill' ) );
//			add_filter( 'mandrill_nl2br', '__return_true' );
		}

		/**
		 * @return mixed
		 */
		public function getIsMainFeatureEnabled() {
			return true;
		}

		/**
		 * @param array $aMessage
		 * @return array
		 */
		public function customiseMandrill( $aMessage ) {
			if ( empty( $aMessage['text'] ) ) {
				$aMessage['text'] = $aMessage['html'];
			}
			return $aMessage;
		}

		/**
		 * @param array $aOptionsParams
		 * @return array
		 * @throws Exception
		 */
		protected function loadStrings_SectionTitles( $aOptionsParams ) {

			$sSectionSlug = $aOptionsParams['section_slug'];
			switch( $aOptionsParams['section_slug'] ) {

				case 'section_email_options' :
					$sTitle = _wpsf__( 'Email Options' );
					break;

				default:
					throw new Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
			}
			$aOptionsParams['title'] = $sTitle;
			return $aOptionsParams;
		}

		/**
		 * @param array $aOptionsParams
		 * @return array
		 * @throws Exception
		 */
		protected function loadStrings_Options( $aOptionsParams ) {

			$sKey = $aOptionsParams['key'];
			switch( $sKey ) {

				case 'block_send_email_address' :
					$sName = _wpsf__( 'Report Email' );
					$sSummary = _wpsf__( 'Where to send email reports' );
					$sDescription = _wpsf__( 'If this is empty, it will default to the blog admin email address.' );
					break;

				case 'send_email_throttle_limit' :
					$sName = _wpsf__( 'Email Throttle Limit' );
					$sSummary = _wpsf__( 'Limit Emails Per Second' );
					$sDescription = _wpsf__( 'You throttle emails sent by this plugin by limiting the number of emails sent every second. This is useful in case you get hit by a bot attack. Zero (0) turns this off. Suggested: 10' );
					break;

				default:
					throw new Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
			}

			$aOptionsParams['name'] = $sName;
			$aOptionsParams['summary'] = $sSummary;
			$aOptionsParams['description'] = $sDescription;
			return $aOptionsParams;
		}

		/**
		 * This is the point where you would want to do any options verification
		 */
		protected function doPrePluginOptionsSave() {
			$sLimit = $this->getOpt( 'send_email_throttle_limit' );
			if ( !is_numeric( $sLimit ) || $sLimit < 0 ) {
				$sLimit = 0;
			}
			$this->setOpt( 'send_email_throttle_limit', $sLimit );
		}

	}

endif;