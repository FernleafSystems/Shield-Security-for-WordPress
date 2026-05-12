<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

trait LookupRouteFormAssertions {

	use HtmlDomAssertions;

	/**
	 * @return array{action: string, html: string}
	 */
	private function extractLookupFormForSubNav( string $html, string $subNav ) :array {
		$xpath = $this->createDomXPathFromHtml( $html );
		$formNodes = $xpath->query( '//form[translate(@method, "GET", "get") = "get"]' );
		$this->assertNotFalse( $formNodes, 'Lookup form query failed.' );

		$matchedForm = null;
		$matchedAction = '';
		foreach ( $formNodes as $formNode ) {
			$action = \html_entity_decode(
				(string)( $formNode instanceof \DOMElement ? $formNode->getAttribute( 'action' ) : '' ),
				\ENT_QUOTES | \ENT_HTML5,
				'UTF-8'
			);
			$query = [];
			\parse_str( (string)\parse_url( $action, \PHP_URL_QUERY ), $query );
			if ( (string)( $query[ Constants::NAV_SUB_ID ] ?? '' ) === $subNav ) {
				$matchedForm = $formNode;
				$matchedAction = $action;
				break;
			}
		}

		$this->assertNotNull(
			$matchedForm,
			\sprintf( 'Lookup form for subnav "%s" missing from render output.', $subNav )
		);

		return [
			'action' => $matchedAction,
			'html'   => $matchedForm instanceof \DOMNode ? $this->nodeOuterHtml( $matchedForm ) : '',
		];
	}

	private function extractHiddenInputValue( string $html, string $name ) :string {
		$xpath = $this->createDomXPathFromHtml( $html );
		$nameQuoted = \sprintf( '"%s"', $name );
		$input = $this->assertXPathExists(
			$xpath,
			'//input[@name='.$nameQuoted.']',
			\sprintf( 'Hidden input "%s" missing from lookup form.', $name )
		);

		return $input instanceof \DOMElement ? (string)$input->getAttribute( 'value' ) : '';
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
