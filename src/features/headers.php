<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Headers;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_Headers extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	protected function doExtraSubmitProcessing() {
		$this->cleanCspHosts();
		$this->cleanCustomRules();
	}

	private function cleanCustomRules() {
		/** @var Headers\Options $oOpts */
		$oOpts = $this->getOptions();
		$oOpts->setOpt( 'xcsp_custom', array_unique( array_filter( array_map(
			function ( $sRule ) {
				$sRule = trim( preg_replace( '#;|\s{2,}#', '', html_entity_decode( $sRule, ENT_QUOTES ) ) );
				if ( !empty( $sRule ) ) {
					$sRule .= ';';
				}
				return $sRule;
			},
			$this->getOpt( 'xcsp_custom', [] )
		) ) ) );
	}

	private function cleanCspHosts() {
		/** @var Headers\Options $oOpts */
		$oOpts = $this->getOptions();

		$aValidDomains = [];
		foreach ( $oOpts->getOpt( 'xcsp_hosts', [] ) as $sDomain ) {
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
			elseif ( strpos( $sDomain, '://' ) && preg_match( '#^([a-zA-Z]+://)#', $sDomain, $aMatches ) ) {
				// there's a protocol specified
				$sCustomProtocol = $aMatches[ 1 ];
				$sDomain = str_replace( $sCustomProtocol, '', $sDomain );
			}

			// First we remove the wildcard and test domain, then add it back later.
			$bWildCard = ( strpos( $sDomain, '*.' ) === 0 );
			if ( $bWildCard ) {
				$sDomain = preg_replace( '#^\*\.#', '', $sDomain );
			}

			if ( !empty ( $sDomain ) && Services::Data()->isValidDomainName( $sDomain ) ) {
				$bValidDomain = true;
			}

			if ( $bValidDomain ) {
				if ( $bWildCard ) {
					$sDomain = '*.'.$sDomain;
				}
				if ( $bHttp ) {
//					$sDomain = 'http://'.$sDomain; // it seems there's no need to "explicitly" state http://
				}
				elseif ( $bHttps ) {
					$sDomain = 'https://'.$sDomain;
				}
				elseif ( !empty( $sCustomProtocol ) ) {
					$sDomain = $sCustomProtocol.$sDomain;
				}
				$aValidDomains[] = $sDomain;
			}
		}
		asort( $aValidDomains );
		$oOpts->setOpt( 'xcsp_hosts', array_unique( $aValidDomains ) );
	}

	/**
	 * @param array $aAllData
	 * @return array
	 */
	public function addInsightsConfigData( $aAllData ) {
		/** @var Headers\Options $oOpts */
		$oOpts = $this->getOptions();

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
			$bAllEnabled = $oOpts->isEnabledXFrame() && $oOpts->isEnabledXssProtection()
						   && $oOpts->isEnabledContentTypeHeader() && $oOpts->isReferrerPolicyEnabled();
			$aThis[ 'key_opts' ][ 'all' ] = [
				'name'    => __( 'HTTP Headers', 'wp-simple-firewall' ),
				'enabled' => $bAllEnabled,
				'summary' => $bAllEnabled ?
					__( 'All important security Headers have been set', 'wp-simple-firewall' )
					: __( "At least one of the HTTP Headers hasn't been set", 'wp-simple-firewall' ),
				'weight'  => 2,
				'href'    => $this->getUrl_DirectLinkToSection( 'section_security_headers' ),
			];
			$bCsp = $oOpts->isEnabledContentSecurityPolicy();
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
	 * @return string
	 */
	protected function getNamespaceBase() {
		return 'Headers';
	}

	/**
	 * @return bool
	 * @deprecated 8.5
	 */
	public function isContentSecurityPolicyEnabled() {
		return $this->isOpt( 'enable_x_content_security_policy', 'Y' );
	}

	/**
	 * @return bool
	 * @deprecated 8.5
	 */
	public function isReferrerPolicyEnabled() {
		return !$this->isOpt( 'x_referrer_policy', 'disabled' );
	}

	/**
	 * @return bool
	 * @deprecated 8.5
	 */
	public function isEnabledXFrame() {
		return in_array( $this->getOpt( 'x_frame' ), [ 'on_sameorigin', 'on_deny' ] );
	}

	/**
	 * @return bool
	 * @deprecated 8.5
	 */
	public function isEnabledXssProtection() {
		return $this->isOpt( 'x_xss_protect', 'Y' );
	}

	/**
	 * @return bool
	 * @deprecated 8.5
	 */
	public function isEnabledContentTypeHeader() {
		return $this->isOpt( 'x_content_type', 'Y' );
	}

	/**
	 * Using this function without first checking isReferrerPolicyEnabled() will result in empty
	 * referrer policy header in the case of "disabled"
	 * @return string
	 * @deprecated 8.5
	 */
	public function getReferrerPolicyValue() {
		$sValue = $this->getOpt( 'x_referrer_policy' );
		return in_array( $sValue, [ 'empty', 'disabled' ] ) ? '' : $sValue;
	}

	/**
	 * @return array
	 * @deprecated 8.5
	 */
	public function getCspHosts() {
		$aHosts = $this->getOpt( 'xcsp_hosts', [] );
		if ( empty( $aHosts ) || !is_array( $aHosts ) ) {
			$aHosts = [];
		}
		return $aHosts;
	}
}