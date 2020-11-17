<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Headers;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class ModCon extends BaseShield\ModCon {

	protected function preProcessOptions() {
		$this->cleanCspHosts();
		$this->cleanCustomRules();
	}

	private function cleanCustomRules() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$opts->setOpt( 'xcsp_custom', array_unique( array_filter( array_map(
			function ( $sRule ) {
				$sRule = trim( preg_replace( '#;|\s{2,}#', '', html_entity_decode( $sRule, ENT_QUOTES ) ) );
				if ( !empty( $sRule ) ) {
					$sRule .= ';';
				}
				return $sRule;
			},
			$opts->getOpt( 'xcsp_custom', [] )
		) ) ) );
	}

	private function cleanCspHosts() {
		/** @var Options $opts */
		$opts = $this->getOptions();

		$aValidDomains = [];
		foreach ( $opts->getOpt( 'xcsp_hosts', [] ) as $sDomain ) {
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
					$this->getOptions()->setOpt( 'xcsp_https', 'Y' );
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
		$opts->setOpt( 'xcsp_hosts', array_unique( $aValidDomains ) );
	}
}