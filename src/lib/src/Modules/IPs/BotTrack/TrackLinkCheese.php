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
	const CHEESE_WORD = 'link-cheese';

	protected function process() {
		add_filter( 'robots_txt', [ $this, 'appendRobotsTxt' ], 15 );
		add_action( 'wp_footer', [ $this, 'insertMouseTrap' ], 0 );
		add_action( 'wp', [ $this, 'testCheese' ], 0 );
	}

	public function testCheese() {
		if ( is_404() && $this->isCheese() ) {

			if ( function_exists( 'wp_robots_sensitive_page' ) ) {
				add_filter( 'wp_robots', 'wp_robots_sensitive_page', 1000 );
			}
			elseif ( function_exists( 'wp_sensitive_page_meta' ) ) {
				if ( !has_action( 'wp_head', 'wp_sensitive_page_meta' ) ) {
					add_action( 'wp_head', 'wp_sensitive_page_meta' );
				}
			}
			elseif ( !has_action( 'wp_head', 'wp_no_robots' ) ) {
				add_action( 'wp_head', 'wp_no_robots' );
			}
			$this->doTransgression();
		}
	}

	/**
	 * @param string $robotsText
	 * @return string
	 */
	public function appendRobotsTxt( $robotsText ) :string {
		$template = Services::WpGeneral()->isPermalinksEnabled() ? "Disallow: /%s/\n" : "Disallow: /*?*%s=\n";
		return rtrim( $robotsText, "\n" )."\n".sprintf( $template, $this->getCheeseWord() );
	}

	private function isCheese() :bool {
		$WP = Services::WpGeneral();

		if ( $WP->isPermalinksEnabled() ) {
			$reqPath = trim( (string)Services::Request()->getPath(), '/' );
			$isCheese = ( $reqPath ===
						  trim( (string)parse_url( $WP->getHomeUrl( $this->getCheeseWord() ), PHP_URL_PATH ), '/' ) )
						|| preg_match( '#icwp-wpsf-[a-z]+-[a-z0-9]{7,9}#', $reqPath ) > 0;
			/** TODO: 10.3 legacy remove */
		}
		else {
			$isCheese = Services::Request()->query( $this->getCheeseWord() ) === '1';
		}

		return $isCheese;
	}

	public function insertMouseTrap() {
		echo sprintf(
			'<style>#%s{display:none !important;}</style><a rel="nofollow" href="%s" title="%s" id="%s">%s</a>',
			'icwpWpsfLinkCheese',
			$this->buildTrapHref(),
			'Click here to see something fantastic',
			'icwpWpsfLinkCheese',
			'Click to access the login or register cheese'
		);
	}

	private function buildTrapHref() :string {
		$WP = Services::WpGeneral();
		return $WP->isPermalinksEnabled() ?
			$WP->getHomeUrl( sprintf( '/%s/', $this->getCheeseWord() ) )
			: add_query_arg( [ $this->getCheeseWord() => '1' ], $WP->getHomeUrl() );
	}

	private function getCheeseWord() :string {
		return $this->getCon()->prefix( self::CHEESE_WORD );
	}
}
