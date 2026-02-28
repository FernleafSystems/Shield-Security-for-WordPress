<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	HtmlDomAssertions,
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class OperatorBreadcrumbHeaderIntegrationTest extends ShieldIntegrationTestCase {

	use HtmlDomAssertions, PluginAdminRouteRenderAssertions;

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	private function renderRoute( string $nav, string $subNav ) :array {
		return $this->withRouteQueryContext(
			$nav,
			$subNav,
			fn() :array => $this->renderPluginAdminRoutePayload( $nav, $subNav )
		);
	}

	private function withRouteQueryContext( string $nav, string $subNav, callable $callback ) :array {
		$servicesRequest = Services::Request();
		$thisRequest = self::con()->this_req->request;

		$snapshotServicesQuery = \is_array( $servicesRequest->query ) ? $servicesRequest->query : [];
		$snapshotThisQuery = \is_array( $thisRequest->query ) ? $thisRequest->query : [];

		$routeQuery = [
			Constants::NAV_ID     => $nav,
			Constants::NAV_SUB_ID => $subNav,
		];
		$servicesRequest->query = \array_merge( $snapshotServicesQuery, $routeQuery );
		$thisRequest->query = \array_merge( $snapshotThisQuery, $routeQuery );

		try {
			$payload = $callback();
			$this->assertIsArray( $payload );
			return $payload;
		}
		finally {
			$servicesRequest->query = $snapshotServicesQuery;
			$thisRequest->query = $snapshotThisQuery;
		}
	}

	private function getHeaderSegmentsFromHtml( string $html ) :array {
		$xpath = $this->createDomXPathFromHtml( $html );
		$headerContainer = $this->assertXPathExists(
			$xpath,
			'//h4[contains(concat(" ", normalize-space(@class), " "), " inner-page-header-title ")]/div',
			'Inner page header segment container'
		);

		$headerText = \preg_replace( '/\s+/u', ' ', \trim( (string)$headerContainer->textContent ) );
		if ( !\is_string( $headerText ) || $headerText === '' ) {
			return [];
		}

		$parts = \preg_split( '/\x{00BB}/u', $headerText );
		if ( !\is_array( $parts ) ) {
			return [];
		}

		$segments = [];
		foreach ( $parts as $part ) {
			$part = \trim( (string)$part );
			if ( $part !== '' ) {
				$segments[] = $part;
			}
		}
		return $segments;
	}

	private function getHeaderBreadcrumbHrefsFromHtml( string $html ) :array {
		$xpath = $this->createDomXPathFromHtml( $html );
		$hrefNodes = $xpath->query(
			'//h4[contains(concat(" ", normalize-space(@class), " "), " inner-page-header-title ")]/div/a[@href]'
		);
		$this->assertNotFalse( $hrefNodes, 'Header breadcrumb href query failed.' );

		$hrefs = [];
		foreach ( $hrefNodes as $hrefNode ) {
			if ( !$hrefNode instanceof \DOMElement ) {
				continue;
			}
			$href = \html_entity_decode(
				(string)$hrefNode->getAttribute( 'href' ),
				\ENT_QUOTES | \ENT_HTML5,
				'UTF-8'
			);
			if ( $href !== '' ) {
				$hrefs[] = $href;
			}
		}
		return $hrefs;
	}

	private function assertNoSelfRouteBreadcrumbLink( array $hrefs, string $nav, string $subNav ) :void {
		$this->assertNotEmpty( $hrefs, 'Expected at least one breadcrumb href in inner page header.' );

		foreach ( $hrefs as $href ) {
			$query = [];
			\parse_str( (string)\parse_url( (string)$href, \PHP_URL_QUERY ), $query );
			$this->assertFalse(
				( $query[ Constants::NAV_ID ] ?? '' ) === $nav
				&& ( $query[ Constants::NAV_SUB_ID ] ?? '' ) === $subNav,
				'Breadcrumb contains self-route link: '.$href
			);
		}
	}

	public function test_investigate_landing_header_avoids_duplicate_terminal_label() :void {
		$payload = $this->renderRoute( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_OVERVIEW );
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$this->assertHtmlContainsMarker( 'Investigate', $html, 'Investigate landing header text marker' );

		$segments = $this->getHeaderSegmentsFromHtml( $html );
		$this->assertSame( [ 'Shield Security', 'Investigate' ], $segments );
		$this->assertNoSelfRouteBreadcrumbLink(
			$this->getHeaderBreadcrumbHrefsFromHtml( $html ),
			PluginNavs::NAV_ACTIVITY,
			PluginNavs::SUBNAV_ACTIVITY_OVERVIEW
		);
	}

	public function test_activity_log_header_uses_normalized_terminal_leaf_title() :void {
		$payload = $this->renderRoute( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_LOGS );
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$this->assertHtmlContainsMarker( 'View Activity Logs', $html, 'Activity log header text marker' );

		$segments = $this->getHeaderSegmentsFromHtml( $html );
		$lastSegment = \end( $segments );
		$this->assertSame( 'View Activity Logs', \is_string( $lastSegment ) ? $lastSegment : '' );
		$this->assertNoSelfRouteBreadcrumbLink(
			$this->getHeaderBreadcrumbHrefsFromHtml( $html ),
			PluginNavs::NAV_ACTIVITY,
			PluginNavs::SUBNAV_LOGS
		);
	}

	public function test_traffic_log_header_uses_normalized_terminal_leaf_title() :void {
		$payload = $this->renderRoute( PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LOGS );
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$this->assertHtmlContainsMarker( 'View HTTP Request Logs', $html, 'Traffic log header text marker' );

		$segments = $this->getHeaderSegmentsFromHtml( $html );
		$lastSegment = \end( $segments );
		$this->assertSame( 'View HTTP Request Logs', \is_string( $lastSegment ) ? $lastSegment : '' );
		$this->assertNoSelfRouteBreadcrumbLink(
			$this->getHeaderBreadcrumbHrefsFromHtml( $html ),
			PluginNavs::NAV_TRAFFIC,
			PluginNavs::SUBNAV_LOGS
		);
	}

	public function test_live_traffic_log_header_uses_normalized_terminal_leaf_title() :void {
		$payload = $this->renderRoute( PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LIVE );
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$this->assertHtmlContainsMarker( 'View Live HTTP Logs', $html, 'Live traffic log header text marker' );

		$segments = $this->getHeaderSegmentsFromHtml( $html );
		$lastSegment = \end( $segments );
		$this->assertSame( 'View Live HTTP Logs', \is_string( $lastSegment ) ? $lastSegment : '' );
		$this->assertNoSelfRouteBreadcrumbLink(
			$this->getHeaderBreadcrumbHrefsFromHtml( $html ),
			PluginNavs::NAV_TRAFFIC,
			PluginNavs::SUBNAV_LIVE
		);
	}
}
