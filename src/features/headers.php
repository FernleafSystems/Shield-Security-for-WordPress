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
		return in_array( $this->getOpt( 'x_frame' ), [ 'on_sameorigin', 'on_deny' ] );
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
		return in_array( $sValue, [ 'empty', 'disabled' ] ) ? '' : $sValue;
	}

	/**
	 * @return array
	 */
	public function getCspHosts() {
		$aHosts = $this->getOpt( 'xcsp_hosts', [] );
		if ( empty( $aHosts ) || !is_array( $aHosts ) ) {
			$aHosts = [];
		}
		return $aHosts;
	}

	protected function doExtraSubmitProcessing() {
		$aDomains = $this->getCspHosts();
		if ( !empty( $aDomains ) && is_array( $aDomains ) ) {
			$oDP = $this->loadDP();
			$aValidDomains = [];
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
		$aThis = [
			'strings'      => [
				'title' => __( 'HTTP Security Headers', 'wp-simple-firewall' ),
				'sub'   => __( 'Protect Visitors With Powerful HTTP Headers', 'wp-simple-firewall' ),
			],
			'key_opts'     => [],
			'href_options' => $this->getUrl_AdminPage()
		];

		if ( !$this->isModOptEnabled() ) {
			$aThis[ 'key_opts' ][ 'mod' ] = $this->getModDisabledInsight();
		}
		else {
			$bAllEnabled = $this->isEnabledXFrame() && $this->isEnabledXssProtection()
						   && $this->isEnabledContentTypeHeader() && $this->isReferrerPolicyEnabled();
			$aThis[ 'key_opts' ][ 'all' ] = [
				'name'    => __( 'HTTP Headers', 'wp-simple-firewall' ),
				'enabled' => $bAllEnabled,
				'summary' => $bAllEnabled ?
					__( 'All important security Headers have been set', 'wp-simple-firewall' )
					: __( "At least one of the HTTP Headers hasn't been set", 'wp-simple-firewall' ),
				'weight'  => 2,
				'href'    => $this->getUrl_DirectLinkToSection( 'section_security_headers' ),
			];
			$bCsp = $this->isContentSecurityPolicyEnabled();
			$aThis[ 'key_opts' ][ 'csp' ] = [
				'name'    => __( 'Content Security Policies', 'wp-simple-firewall' ),
				'enabled' => $bCsp,
				'summary' => $bCsp ?
					__( 'Content Security Policy is turned on', 'wp-simple-firewall' )
					: __( "Content Security Policies aren't active", 'wp-simple-firewall' ),
				'weight'  => 1,
				'href'    => $this->getUrl_DirectLinkToSection( 'section_content_security_policy' ),
			];
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
				$sTitleShort = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$sTitle = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Protect visitors to your site by implementing increased security response headers.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Enabling these features are advised, but you must test them on your site thoroughly.', 'wp-simple-firewall' ) )
				];
				break;

			case 'section_security_headers' :
				$sTitle = __( 'Advanced Security Headers', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Protect visitors to your site by implementing increased security response headers.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Enabling these features are advised, but you must test them on your site thoroughly.', 'wp-simple-firewall' ) )
				];
				$sTitleShort = __( 'Security Headers', 'wp-simple-firewall' );
				break;

			case 'section_content_security_policy' :
				$sTitle = __( 'Content Security Policy', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Restrict the sources and types of content that may be loaded and processed by visitor browsers.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Enabling these features are advised, but you must test them on your site thoroughly.', 'wp-simple-firewall' ) )
				];
				$sTitleShort = __( 'Content Security Policy', 'wp-simple-firewall' );
				break;

			default:
				throw new \Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
		}
		$aOptionsParams[ 'title' ] = $sTitle;
		$aOptionsParams[ 'summary' ] = ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : [];
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
				$sName = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				$sSummary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				$sDescription = sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				break;

			case 'x_frame' :
				$sName = __( 'Block iFrames', 'wp-simple-firewall' );
				$sSummary = __( 'Block Remote iFrames Of This Site', 'wp-simple-firewall' );
				$sDescription = __( 'The setting prevents any external website from embedding your site in an iFrame.', 'wp-simple-firewall' )
								.__( 'This is useful for preventing so-called "ClickJack attacks".', 'wp-simple-firewall' );
				break;

			case 'x_referrer_policy' :
				$sName = __( 'Referrer Policy', 'wp-simple-firewall' );
				$sSummary = __( 'Referrer Policy Header', 'wp-simple-firewall' );
				$sDescription = __( 'The Referrer Policy Header allows you to control when and what referral information a browser may pass along with links clicked on your site.', 'wp-simple-firewall' );
				break;

			case 'x_xss_protect' :
				$sName = __( 'XSS Protection', 'wp-simple-firewall' );
				$sSummary = __( 'Employ Built-In Browser XSS Protection', 'wp-simple-firewall' );
				$sDescription = __( 'Directs compatible browsers to block what they detect as Reflective XSS attacks.', 'wp-simple-firewall' );
				break;

			case 'x_content_type' :
				$sName = __( 'Prevent Mime-Sniff', 'wp-simple-firewall' );
				$sSummary = __( 'Turn-Off Browser Mime-Sniff', 'wp-simple-firewall' );
				$sDescription = __( 'Reduces visitor exposure to malicious user-uploaded content.', 'wp-simple-firewall' );
				break;

			case 'enable_x_content_security_policy' :
				$sName = sprintf( __( 'Enable %s', 'wp-simple-firewall' ), 'CSP' );
				$sSummary = sprintf( __( 'Enable %s', 'wp-simple-firewall' ), __( 'Content Security Policy', 'wp-simple-firewall' ) );
				$sDescription = __( 'Allows for permission and restriction of all resources loaded on your site.', 'wp-simple-firewall' );
				break;

			case 'xcsp_self' :
				$sName = __( 'Self', 'wp-simple-firewall' );
				$sSummary = __( "Allow 'self' Directive", 'wp-simple-firewall' );
				$sDescription = __( "Using 'self' is generally recommended.", 'wp-simple-firewall' )
								.__( "It essentially means that resources from your own host:protocol are permitted.", 'wp-simple-firewall' );
				break;

			case 'xcsp_inline' :
				$sName = __( 'Inline Entities', 'wp-simple-firewall' );
				$sSummary = __( 'Allow Inline Scripts and CSS', 'wp-simple-firewall' );
				$sDescription = __( 'Allows parsing of Javascript and CSS declared in-line in your html document.', 'wp-simple-firewall' );
				break;

			case 'xcsp_data' :
				$sName = __( 'Embedded Data', 'wp-simple-firewall' );
				$sSummary = __( 'Allow "data:" Directives', 'wp-simple-firewall' );
				$sDescription = __( 'Allows use of embedded data directives, most commonly used for images and fonts.', 'wp-simple-firewall' );
				break;

			case 'xcsp_eval' :
				$sName = __( 'Allow eval()', 'wp-simple-firewall' );
				$sSummary = __( 'Allow Javascript eval()', 'wp-simple-firewall' );
				$sDescription = __( 'Permits the use of Javascript the eval() function.', 'wp-simple-firewall' );
				break;

			case 'xcsp_https' :
				$sName = __( 'HTTPS', 'wp-simple-firewall' );
				$sSummary = __( 'HTTPS Resource Loading', 'wp-simple-firewall' );
				$sDescription = __( 'Allows loading of any content provided over HTTPS.', 'wp-simple-firewall' );
				break;

			case 'xcsp_hosts' :
				$sName = __( 'Permitted Hosts', 'wp-simple-firewall' );
				$sSummary = __( 'Permitted Hosts and Domains', 'wp-simple-firewall' );
				$sDescription = __( 'You can explicitly state which hosts/domain from which content may be loaded.', 'wp-simple-firewall' )
								.' '.__( 'Take great care and test your site as you may block legitimate resources.', 'wp-simple-firewall' )
								.'<br />- '.__( 'If in-doubt, leave blank or use "*" only.', 'wp-simple-firewall' )
								.'<br />- '.sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), __( 'You can force only HTTPS for a given domain by prefixing it with "https://".', 'wp-simple-firewall' ) );
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