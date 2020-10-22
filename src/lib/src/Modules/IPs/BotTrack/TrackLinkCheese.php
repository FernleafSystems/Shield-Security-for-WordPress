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
	 * @param string $robotsText
	 * @return string
	 */
	public function appendRobotsTxt( $robotsText ) {
		$template = Services::WpGeneral()->isPermalinksEnabled() ? "Disallow: /%s-*/\n" : "Disallow: /*?*%s=\n";
		$robotsText = rtrim( $robotsText, "\n" )."\n";
		foreach ( $this->getPossibleWords() as $word ) {
			$robotsText .= sprintf( $template, $this->getCon()->prefix( $word ) );
		}
		return $robotsText;
	}

	private function isCheese() {
		$con = $this->getCon();
		$req = Services::Request();

		$bIsCheese = false;
		if ( Services::WpGeneral()->isPermalinksEnabled() ) {
			preg_match(
				sprintf( '#%s-(%s)-([a-z0-9]{7,9})$#i', $con->prefix(), implode( '|', $this->getPossibleWords() ) ),
				trim( $req->getPath(), '/' ),
				$aMatches
			);
			$bIsCheese = isset( $aMatches[ 2 ] );
		}
		else {
			foreach ( $this->getPossibleWords() as $word ) {
				if ( preg_match( '#^[a-z0-9]{7,9}$#i', $req->query( $con->prefix( $word ) ) ) ) {
					$bIsCheese = true;
					break;
				}
			}
		}

		return $bIsCheese;
	}

	public function insertMouseTrap() {
		$id = chr( rand( 97, 122 ) ).rand( 1000, 10000000 );
		echo sprintf(
			'<style>#%s{display:none !important;}</style><a rel="nofollow" href="%s" title="%s" id="%s">%s</a>',
			$id, $this->buildTrapHref(), 'Click here to see something fantastic',
			$id, 'Click to access the login or register cheese'
		);
	}

	/**
	 * @return string
	 */
	private function buildTrapHref() {
		$con = $this->getCon();

		$oWP = Services::WpGeneral();
		$sKey = substr( md5( wp_generate_password() ), 5, rand( 7, 9 ) );
		$sWord = $this->getPossibleWords()[ rand( 1, count( $this->getPossibleWords() ) ) - 1 ];
		if ( $oWP->isPermalinksEnabled() ) {
			$sLink = $oWP->getHomeUrl( sprintf( '/%s-%s/', $con->prefix( $sWord ), $sKey ) );
		}
		else {
			$sLink = add_query_arg( [ $con->prefix( $sWord ) => $sKey ], $oWP->getHomeUrl() );
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
