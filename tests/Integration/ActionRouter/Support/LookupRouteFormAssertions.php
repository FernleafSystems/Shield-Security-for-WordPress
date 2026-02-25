<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

trait LookupRouteFormAssertions {

	/**
	 * @return array{action: string, html: string}
	 */
	private function extractLookupFormForSubNav( string $html, string $subNav ) :array {
		$subNavPattern = \preg_quote( $subNav, '#' );
		$pattern = '#<form[^>]*method="get"[^>]*action="([^"]*nav_sub='.$subNavPattern.'[^"]*)"[^>]*>(.*?)</form>#si';
		$matched = \preg_match( $pattern, $html, $matches ) === 1;
		$this->assertTrue( $matched, \sprintf( 'Lookup form for subnav "%s" missing from render output.', $subNav ) );

		return [
			'action' => \html_entity_decode( (string)( $matches[ 1 ] ?? '' ), \ENT_QUOTES, 'UTF-8' ),
			'html'   => (string)( $matches[ 2 ] ?? '' ),
		];
	}

	private function extractHiddenInputValue( string $html, string $name ) :string {
		$namePattern = \preg_quote( $name, '#' );
		$pattern = '#<input\b(?=[^>]*\bname="'.$namePattern.'")(?=[^>]*\bvalue="([^"]*)")[^>]*>#i';
		$matched = \preg_match( $pattern, $html, $matches ) === 1;
		$this->assertTrue( $matched, \sprintf( 'Hidden input "%s" missing from lookup form.', $name ) );
		return (string)( $matches[ 1 ] ?? '' );
	}

	private function assertLookupFormRouteContract( array $form, string $expectedSubNav ) :void {
		$query = [];
		\parse_str( (string)\parse_url( (string)$form[ 'action' ], \PHP_URL_QUERY ), $query );
		$this->assertSame( self::con()->plugin_urls->rootAdminPageSlug(), (string)( $query[ 'page' ] ?? '' ) );
		$this->assertSame( PluginNavs::NAV_ACTIVITY, (string)( $query[ Constants::NAV_ID ] ?? '' ) );
		$this->assertSame( $expectedSubNav, (string)( $query[ Constants::NAV_SUB_ID ] ?? '' ) );

		$this->assertSame( self::con()->plugin_urls->rootAdminPageSlug(), $this->extractHiddenInputValue( (string)$form[ 'html' ], 'page' ) );
		$this->assertSame( PluginNavs::NAV_ACTIVITY, $this->extractHiddenInputValue( (string)$form[ 'html' ], 'nav' ) );
		$this->assertSame( $expectedSubNav, $this->extractHiddenInputValue( (string)$form[ 'html' ], 'nav_sub' ) );
	}
}

