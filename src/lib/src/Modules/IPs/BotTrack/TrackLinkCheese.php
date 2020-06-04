<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\BotTrack;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Works by inserting a random, nofollow link to the footer of the page and appending to robots.txt
 * Class LinkCheese
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\BotTrack
 */
class TrackLinkCheese extends Base {

	const OPT_KEY = 'track_linkcheese';

	protected function process() {
		add_filter( 'robots_txt', [ $this, 'appendRobotsTxt' ], 15 );
		add_action( 'wp_footer', [ $this, 'insertMouseTrap' ], 0 );
		if ( $this->isCheese() ) {
			$this->doTransgression();
		}
	}

	/**
	 * @param string $sRobotsText
	 * @return string
	 */
	public function appendRobotsTxt( $sRobotsText ) {
		$sTempl = Services::WpGeneral()->isPermalinksEnabled() ? "Disallow: /%s-*/\n" : "Disallow: /*?*%s=\n";
		$sRobotsText = rtrim( $sRobotsText, "\n" )."\n";
		foreach ( $this->getPossibleWords() as $sWord ) {
			$sRobotsText .= sprintf( $sTempl, $this->getMod()->prefix( $sWord ) );
		}
		return $sRobotsText;
	}

	private function isCheese() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oFO */
		$oFO = $this->getMod();
		$oReq = Services::Request();

		$bIsCheese = false;
		if ( Services::WpGeneral()->isPermalinksEnabled() ) {
			preg_match(
				sprintf( '#^%s-(%s)-([a-z0-9]{7,9})$#i', $oFO->prefix(), implode( '|', $this->getPossibleWords() ) ),
				trim( $oReq->getPath(), '/' ),
				$aMatches
			);
			$bIsCheese = isset( $aMatches[ 2 ] );
		}
		else {
			foreach ( $this->getPossibleWords() as $sWord ) {
				if ( preg_match( '#^[a-z0-9]{7,9}$#i', $oReq->query( $oFO->prefix( $sWord ) ) ) ) {
					$bIsCheese = true;
					break;
				}
			}
		}

		return $bIsCheese;
	}

	public function insertMouseTrap() {
		$sId = chr( rand( 97, 122 ) ).rand( 1000, 10000000 );
		echo sprintf(
			'<style>#%s{display:none !important;}</style><a rel="nofollow" href="%s" title="%s" id="%s">%s</a>',
			$sId, $this->buildTrapHref(), 'Click here to see something fantastic',
			$sId, 'Click to access the login or register cheese'
		);
	}

	/**
	 * @return string
	 */
	private function buildTrapHref() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oFO */
		$oFO = $this->getMod();

		$oWp = Services::WpGeneral();
		$sKey = substr( md5( wp_generate_password() ), 5, rand( 7, 9 ) );
		$sWord = $this->getPossibleWords()[ rand( 1, count( $this->getPossibleWords() ) ) - 1 ];
		if ( $oWp->isPermalinksEnabled() ) {
			$sLink = $oWp->getHomeUrl( sprintf( '/%s-%s/', $oFO->prefix( $sWord ), $sKey ) );
		}
		else {
			$sLink = add_query_arg( [ $oFO->prefix( $sWord ) => $sKey ], $oWp->getHomeUrl() );
		}
		return $sLink;
	}

	/**
	 * @return string[]
	 */
	private function getPossibleWords() {
		return [
			'mouse',
			'cheese',
			'venus',
			'stilton',
			'cheddar',
			'holey',
		];
	}
}
