<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\BotTrack;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

/**
 * Works by inserting a random, nofollow link to the footer of the page and appending to robots.txt
 */
class TrackLinkCheese extends Base {

	public const OPT_KEY = 'track_linkcheese';
	private const CHEESE_WORD = 'link-cheese';

	protected function canRun() :bool {
		$isSplit = \trim( (string)\wp_parse_url( Services::WpGeneral()->getHomeUrl(), \PHP_URL_PATH ), '/' )
				   !== \trim( (string)\wp_parse_url( Services::WpGeneral()->getWpUrl(), \PHP_URL_PATH ), '/' );
		return !Services::WpFs()->exists( path_join( ABSPATH, 'robots.txt' ) )
			   && ( !$isSplit || !Services::WpFs()->exists( path_join( \dirname( ABSPATH ), 'robots.txt' ) ) );
	}

	protected function process() {
		add_filter( 'robots_txt', [ $this, 'appendRobotsTxt' ], 15 );
		add_action( 'wp_footer', [ $this, 'insertMouseTrap' ], 0 );
		add_action( 'wp', [ $this, 'testCheese' ] );
	}

	public function testCheese() {
		if ( ( is_404() || is_front_page() ) && $this->isCheese() ) {

			add_filter( 'wp_robots', function ( $robots ) {
				if ( \is_array( $robots ) ) {
					$robots = \array_merge( $robots, [
						'follow'    => false,
						'index'     => false,
						'archive'   => false,
						'noindex'   => true,
						'nofollow'  => true,
						'noarchive' => true,
					] );
				}
				return $robots;
			}, \PHP_INT_MAX );

			if ( !Services::WpUsers()->isUserLoggedIn() ) {
				$this->doTransgression();
			}
		}
	}

	/**
	 * @param string $robotsText
	 */
	public function appendRobotsTxt( $robotsText ) :string {
		$WP = Services::WpGeneral();
		return sprintf( "%s\n\n%s\n",
			\rtrim( $robotsText, "\n" ),
			\implode( "\n", [
				'# SHIELD::START',
				'# ------------------------',
				'User-agent: *',
				sprintf( $WP->isPermalinksEnabled() ? "Disallow: /%s/" : "Disallow: /*?*%s=", $this->getCheeseWord() ),
				'# ------------------------',
				'# SHIELD::END',
			] )
		);
	}

	private function isCheese() :bool {
		$req = Services::Request();
		$WP = Services::WpGeneral();

		return $WP->isPermalinksEnabled() ?
			\trim( $req->getPath(), '/' ) === \trim( (string)\wp_parse_url( $WP->getHomeUrl( $this->getCheeseWord() ), \PHP_URL_PATH ), '/' )
			: $req->query( $this->getCheeseWord() ) == '1';
	}

	public function insertMouseTrap() {
		echo \sprintf(
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
			$WP->getHomeUrl( sprintf( '/%s/', $this->getCheeseWord() ) ) : URL::Build( $WP->getHomeUrl(), [ $this->getCheeseWord() => '1' ] );
	}

	private function getCheeseWord() :string {
		return self::con()->prefix( self::CHEESE_WORD );
	}
}
