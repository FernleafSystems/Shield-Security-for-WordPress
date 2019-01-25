<?php

class ICWP_WPSF_FeatureHandler_Headers extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @return boolean
	 */
	public function isContentSecurityPolicyEnabled() {
		return $this->isOpt( 'enable_x_content_security_policy', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isReferrerPolicyEnabled() {
		return !$this->isOpt( 'x_referrer_policy', 'disabled' );
	}

	/**
	 * @return bool
	 */
	public function isEnabledXFrame() {
		return in_array( $this->getOpt( 'x_frame' ), array( 'on_sameorigin', 'on_deny' ) );
	}

	/**
	 * @return bool
	 */
	public function isEnabledXssProtection() {
		return $this->isOpt( 'x_xss_protect', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isEnabledContentTypeHeader() {
		return $this->isOpt( 'x_content_type', 'Y' );
	}

	/**
	 * Using this function without first checking isReferrerPolicyEnabled() will result in empty
	 * referrer policy header in the case of "disabled"
	 * @return string
	 */
	public function getReferrerPolicyValue() {
		$sValue = $this->getOpt( 'x_referrer_policy' );
		return in_array( $sValue, array( 'empty', 'disabled' ) ) ? '' : $sValue;
	}

	/**
	 * @return array
	 */
	public function getCspHosts() {
		$aHosts = $this->getOpt( 'xcsp_hosts', array() );
		if ( empty( $aHosts ) || !is_array( $aHosts ) ) {
			$aHosts = array();
		}
		return $aHosts;
	}

	protected function doExtraSubmitProcessing() {
		$aDomains = $this->getCspHosts();
		if ( !empty( $aDomains ) && is_array( $aDomains ) ) {
			$oDP = $this->loadDP();
			$aValidDomains = array();
			foreach ( $aDomains as $sDomain ) {
				$bValidDomain = false;
				$sDomain = trim( $sDomain );

				$bHttps = ( strpos( $sDomain, 'https://' ) === 0 );
				$bHttp = ( strpos( $sDomain, 'http://' ) === 0 );
				if ( $bHttp || $bHttps ) {
					$sDomain = preg_replace( '#^http(s)?://#', '', $sDomain );
				}

				$sCustomProtocol = '';
				// Special wildcard case
				if ( $sDomain == '*' ) {
					if ( $bHttps ) {
						$this->setOpt( 'xcsp_https', 'Y' );
					}
					else {
						$bValidDomain = true;
					}
				}
				else if ( strpos( $sDomain, '://' ) && preg_match( '#^([a-zA-Z]+://)#', $sDomain, $aMatches ) ) {
					// there's a protocol specified
					$sCustomProtocol = $aMatches[ 1 ];
					$sDomain = str_replace( $sCustomProtocol, '', $sDomain );
				}

				// First we remove the wildcard and test domain, then add it back later.
				$bWildCard = ( strpos( $sDomain, '*.' ) === 0 );
				if ( $bWildCard ) {
					$sDomain = preg_replace( '#^\*\.#', '', $sDomain );
				}

				if ( !empty ( $sDomain ) && $oDP->isValidDomainName( $sDomain ) ) {
					$bValidDomain = true;
				}

				if ( $bValidDomain ) {
					if ( $bWildCard ) {
						$sDomain = '*.'.$sDomain;
					}
					if ( $bHttp ) {
//							$sDomain = 'http://'.$sDomain; // it seems there's no need to "explicitly" state http://
					}
					else if ( $bHttps ) {
						$sDomain = 'https://'.$sDomain;
					}
					else if ( !empty( $sCustomProtocol ) ) {
						$sDomain = $sCustomProtocol.$sDomain;
					}
					$aValidDomains[] = $sDomain;
				}
			}
			asort( $aValidDomains );
			$aValidDomains = array_unique( $aValidDomains );
			$this->setOpt( 'xcsp_hosts', $aValidDomains );
		}
	}

	/**
	 * @param array $aAllData
	 * @return array
	 */
	public function addInsightsConfigData( $aAllData ) {
		$aThis = array(
			'strings'      => array(
				'title' => _wpsf__( 'HTTP Security Headers' ),
				'sub'   => _wpsf__( 'Protect Visitors With Powerful HTTP Headers' ),
			),
			'key_opts'     => array(),
			'href_options' => $this->getUrl_AdminPage()
		);

		if ( !$this->isModOptEnabled() ) {
			$aThis[ 'key_opts' ][ 'mod' ] = $this->getModDisabledInsight();
		}
		else {
			$bAllEnabled = $this->isEnabledXFrame() && $this->isEnabledXssProtection()
						   && $this->isEnabledContentTypeHeader() && $this->isReferrerPolicyEnabled();
			$aThis[ 'key_opts' ][ 'all' ] = array(
				'name'    => _wpsf__( 'HTTP Headers' ),
				'enabled' => $bAllEnabled,
				'summary' => $bAllEnabled ?
					_wpsf__( 'All important security Headers have been set' )
					: _wpsf__( "At least one of the HTTP Headers hasn't been set" ),
				'weight'  => 2,
				'href'    => $this->getUrl_DirectLinkToSection( 'section_security_headers' ),
			);
			$bCsp = $this->isContentSecurityPolicyEnabled();
			$aThis[ 'key_opts' ][ 'csp' ] = array(
				'name'    => _wpsf__( 'Content Security Policies' ),
				'enabled' => $bCsp,
				'summary' => $bCsp ?
					_wpsf__( 'Content Security Policy is turned on' )
					: _wpsf__( "Content Security Policies aren't active" ),
				'weight'  => 1,
				'href'    => $this->getUrl_DirectLinkToSection( 'section_content_security_policy' ),
			);
		}

		$aAllData[ $this->getSlug() ] = $aThis;
		return $aAllData;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws \Exception
	 */
	protected function loadStrings_SectionTitles( $aOptionsParams ) {

		$sSectionSlug = $aOptionsParams[ 'slug' ];
		switch ( $sSectionSlug ) {

			case 'section_enable_plugin_feature_headers' :
				$sTitle = sprintf( _wpsf__( 'Enable Module: %s' ), $this->getMainFeatureName() );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'Protect visitors to your site by implementing increased security response headers.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), _wpsf__( 'Enabling these features are advised, but you must test them on your site thoroughly.' ) )
				);
				$sTitleShort = sprintf( _wpsf__( '%s/%s Module' ), _wpsf__( 'Enable' ), _wpsf__( 'Disable' ) );
				break;

			case 'section_security_headers' :
				$sTitle = _wpsf__( 'Advanced Security Headers' );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'Protect visitors to your site by implementing increased security response headers.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), _wpsf__( 'Enabling these features are advised, but you must test them on your site thoroughly.' ) )
				);
				$sTitleShort = _wpsf__( 'Security Headers' );
				break;

			case 'section_content_security_policy' :
				$sTitle = _wpsf__( 'Content Security Policy' );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'Restrict the sources and types of content that may be loaded and processed by visitor browsers.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), _wpsf__( 'Enabling these features are advised, but you must test them on your site thoroughly.' ) )
				);
				$sTitleShort = _wpsf__( 'Content Security Policy' );
				break;

			default:
				throw new \Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
		}
		$aOptionsParams[ 'title' ] = $sTitle;
		$aOptionsParams[ 'summary' ] = ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : array();
		$aOptionsParams[ 'title_short' ] = $sTitleShort;
		return $aOptionsParams;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws \Exception
	 */
	protected function loadStrings_Options( $aOptionsParams ) {

		$sKey = $aOptionsParams[ 'key' ];
		switch ( $sKey ) {

			case 'enable_headers' :
				$sName = sprintf( _wpsf__( 'Enable %s Module' ), $this->getMainFeatureName() );
				$sSummary = sprintf( _wpsf__( 'Enable (or Disable) The %s Module' ), $this->getMainFeatureName() );
				$sDescription = sprintf( _wpsf__( 'Un-Checking this option will completely disable the %s module.' ), $this->getMainFeatureName() );
				break;

			case 'x_frame' :
				$sName = _wpsf__( 'Block iFrames' );
				$sSummary = _wpsf__( 'Block Remote iFrames Of This Site' );
				$sDescription = _wpsf__( 'The setting prevents any external website from embedding your site in an iFrame.' )
								._wpsf__( 'This is useful for preventing so-called "ClickJack attacks".' );
				break;

			case 'x_referrer_policy' :
				$sName = _wpsf__( 'Referrer Policy' );
				$sSummary = _wpsf__( 'Referrer Policy Header' );
				$sDescription = _wpsf__( 'The Referrer Policy Header allows you to control when and what referral information a browser may pass along with links clicked on your site.' );
				break;

			case 'x_xss_protect' :
				$sName = _wpsf__( 'XSS Protection' );
				$sSummary = _wpsf__( 'Employ Built-In Browser XSS Protection' );
				$sDescription = _wpsf__( 'Directs compatible browsers to block what they detect as Reflective XSS attacks.' );
				break;

			case 'x_content_type' :
				$sName = _wpsf__( 'Prevent Mime-Sniff' );
				$sSummary = _wpsf__( 'Turn-Off Browser Mime-Sniff' );
				$sDescription = _wpsf__( 'Reduces visitor exposure to malicious user-uploaded content.' );
				break;

			case 'enable_x_content_security_policy' :
				$sName = sprintf( _wpsf__( 'Enable %s' ), 'CSP' );
				$sSummary = sprintf( _wpsf__( 'Enable %s' ), _wpsf__( 'Content Security Policy' ) );
				$sDescription = _wpsf__( 'Allows for permission and restriction of all resources loaded on your site.' );
				break;

			case 'xcsp_self' :
				$sName = _wpsf__( 'Self' );
				$sSummary = _wpsf__( "Allow 'self' Directive" );
				$sDescription = _wpsf__( "Using 'self' is generally recommended." )
								._wpsf__( "It essentially means that resources from your own host:protocol are permitted." );
				break;

			case 'xcsp_inline' :
				$sName = _wpsf__( 'Inline Entities' );
				$sSummary = _wpsf__( 'Allow Inline Scripts and CSS' );
				$sDescription = _wpsf__( 'Allows parsing of Javascript and CSS declared in-line in your html document.' );
				break;

			case 'xcsp_data' :
				$sName = _wpsf__( 'Embedded Data' );
				$sSummary = _wpsf__( 'Allow "data:" Directives' );
				$sDescription = _wpsf__( 'Allows use of embedded data directives, most commonly used for images and fonts.' );
				break;

			case 'xcsp_eval' :
				$sName = _wpsf__( 'Allow eval()' );
				$sSummary = _wpsf__( 'Allow Javascript eval()' );
				$sDescription = _wpsf__( 'Permits the use of Javascript the eval() function.' );
				break;

			case 'xcsp_https' :
				$sName = _wpsf__( 'HTTPS' );
				$sSummary = _wpsf__( 'HTTPS Resource Loading' );
				$sDescription = _wpsf__( 'Allows loading of any content provided over HTTPS.' );
				break;

			case 'xcsp_hosts' :
				$sName = _wpsf__( 'Permitted Hosts' );
				$sSummary = _wpsf__( 'Permitted Hosts and Domains' );
				$sDescription = _wpsf__( 'You can explicitly state which hosts/domain from which content may be loaded.' )
								.' '._wpsf__( 'Take great care and test your site as you may block legitimate resources.' )
								.'<br />- '._wpsf__( 'If in-doubt, leave blank or use "*" only.' )
								.'<br />- '.sprintf( '%s: %s', _wpsf__( 'Note' ), _wpsf__( 'You can force only HTTPS for a given domain by prefixing it with "https://".' ) );
				break;

			default:
				throw new \Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
		}

		$aOptionsParams[ 'name' ] = $sName;
		$aOptionsParams[ 'summary' ] = $sSummary;
		$aOptionsParams[ 'description' ] = $sDescription;
		return $aOptionsParams;
	}
}