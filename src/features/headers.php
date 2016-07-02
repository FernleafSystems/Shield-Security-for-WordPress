<?php

if ( !class_exists( 'ICWP_WPSF_FeatureHandler_Headers', false ) ):

	require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'base_wpsf.php' );

	class ICWP_WPSF_FeatureHandler_Headers extends ICWP_WPSF_FeatureHandler_BaseWpsf {

		/**
		 * @return array
		 */
		public function getContentSecurityPolicyDomains() {
			return $this->getOpt( 'x_content_security_policy' );
		}

		protected function doExecuteProcessor() {
			if ( ! apply_filters( $this->doPluginPrefix( 'visitor_is_whitelisted' ), false ) ) {
				parent::doExecuteProcessor();
			}
		}

		protected function doExtraSubmitProcessing() {
			$aDomains = $this->getOpt( 'x_content_security_policy' );
			if ( !empty( $aDomains ) ) {
				$oDP = $this->loadDataProcessor();
				$aValidDomains = array();
				foreach ( $aDomains as $sDomain ) {
					$sDomain = trim( $sDomain );

					// First we remove the wildcard and test domain, then add it back later.
					$bWildCard = ( strpos( $sDomain, '*.' ) === 0 );
					if ( $bWildCard ) {
						$sDomain = str_replace( '*.', '', $sDomain );
					}

					if ( empty ( $sDomain ) ) {
						continue;
					}

					if ( $oDP->isValidDomainName( $sDomain ) ) {
						if ( $bWildCard ) {
							$sDomain = '*.' . $sDomain;
						}
						$aValidDomains[] = $sDomain;
					}
				}
				asort( $aValidDomains );
				$aValidDomains = array_unique( $aValidDomains );
				$this->setOpt( 'x_content_security_policy', $aValidDomains );
			}
		}

		/**
		 * @param array $aOptionsParams
		 * @return array
		 * @throws Exception
		 */
		protected function loadStrings_SectionTitles( $aOptionsParams ) {

			$sSectionSlug = $aOptionsParams['section_slug'];
			switch( $aOptionsParams['section_slug'] ) {

				case 'section_enable_plugin_feature_headers' :
					$sTitle = sprintf( _wpsf__( 'Enable Plugin Feature: %s' ), $this->getMainFeatureName() );
					$aSummary = array(
						sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'Protect visitors to your site by implementing increased security response headers.' ) ),
						sprintf( _wpsf__( 'Recommendation - %s' ), _wpsf__( 'Enabling these features are advised, but you must test them on your site thoroughly.' ) )
					);
					$sTitleShort = sprintf( '%s / %s', _wpsf__( 'Enable' ), _wpsf__( 'Disable' ) );
					break;

				case 'section_security_headers' :
					$sTitle = _wpsf__( 'Advanced Security Headers' );
					$aSummary = array(
						sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'Protect visitors to your site by implementing increased security response headers.' ) ),
						sprintf( _wpsf__( 'Recommendation - %s' ), _wpsf__( 'Enabling these features are advised, but you must test them on your site thoroughly.' ) )
					);
					$sTitleShort = _wpsf__( 'Security Headers' );
					break;

				case 'section_content_security_policy' :
					$sTitle = _wpsf__( 'Content Security Policy' );
					$aSummary = array(
						sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'Protect visitors to your site by implementing increased security response headers.' ) ),
						sprintf( _wpsf__( 'Recommendation - %s' ), _wpsf__( 'Enabling these features are advised, but you must test them on your site thoroughly.' ) )
					);
					$sTitleShort = _wpsf__( 'Content Security Policy' );
					break;

				default:
					throw new Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
			}
			$aOptionsParams['section_title'] = $sTitle;
			$aOptionsParams['section_summary'] = ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : array();
			$aOptionsParams['section_title_short'] = $sTitleShort;
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

				case 'enable_headers' :
					$sName = sprintf( _wpsf__( 'Enable %s' ), $this->getMainFeatureName() );
					$sSummary = sprintf( _wpsf__( 'Enable (or Disable) The %s Feature' ), $this->getMainFeatureName() );
					$sDescription = sprintf( _wpsf__( 'Checking/Un-Checking this option will completely turn on/off the whole %s feature.' ), $this->getMainFeatureName() );
					break;

				case 'x_frame' :
					$sName = _wpsf__( 'Block iFrames' );
					$sSummary = _wpsf__( 'Block Remote iFrames Of This Site' );
					$sDescription = _wpsf__( 'The setting prevents any external website from embedding your site in an iFrame.' )
						._wpsf__( 'This is useful for preventing so-called "ClickJack attacks".' );
					break;

				case 'x_xss_protect' :
					$sName = _wpsf__( 'XSS Protection' );
					$sSummary = _wpsf__( 'Employ Built-In Browser XSS Protection' );
					$sDescription = _wpsf__( 'Directs compatible browser to block what they detect as Reflective XSS attacks.' );
					break;

				case 'x_content_type' :
					$sName = _wpsf__( 'Prevent Mime-Sniff' );
					$sSummary = _wpsf__( 'Turn-Off Browser Mime-Sniff' );
					$sDescription = _wpsf__( 'Reduces visitor exposure to malicious user-uploaded content.' );
					break;

				case 'x_content_security_policy' :
					$sName = _wpsf__( 'Content Security Policy' );
					$sSummary = _wpsf__( 'Content Security Policy' );
					$sDescription = _wpsf__( 'Prevents loading of any assets from any domains you do not specify.' );
					break;

				default:
					throw new Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
			}

			$aOptionsParams['name'] = $sName;
			$aOptionsParams['summary'] = $sSummary;
			$aOptionsParams['description'] = $sDescription;
			return $aOptionsParams;
		}
	}

endif;