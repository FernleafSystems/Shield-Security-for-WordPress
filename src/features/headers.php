<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Headers;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_Headers extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	protected function preProcessOptions() {
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
	 * @return string
	 */
	protected function getNamespaceBase() {
		return 'Headers';
	}
}