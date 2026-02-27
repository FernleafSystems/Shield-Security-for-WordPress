<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\PluginAdminRouteRenderAssertions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class OperatorBreadcrumbHeaderIntegrationTest extends ShieldIntegrationTestCase {

	use PluginAdminRouteRenderAssertions;

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	private function renderRoute( string $nav, string $subNav ) :array {
		return $this->renderPluginAdminRoutePayload( $nav, $subNav );
	}

	private function getHeaderSegments( array $payload ) :array {
		$segments = $payload[ 'render_data' ][ 'hrefs' ][ 'inner_page_header_segments' ] ?? [];
		$this->assertIsArray( $segments );
		return $segments;
	}

	private function assertNoSelfRouteBreadcrumbLink( array $segments, string $nav, string $subNav ) :void {
		foreach ( $segments as $segment ) {
			$href = \html_entity_decode( (string)( $segment[ 'href' ] ?? '' ), \ENT_QUOTES, 'UTF-8' );
			if ( $href === '' ) {
				continue;
			}
			$query = [];
			\parse_str( (string)\parse_url( $href, \PHP_URL_QUERY ), $query );
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

		$segments = $this->getHeaderSegments( $payload );
		$this->assertSame( [ 'Shield Security', 'Investigate' ], \array_column( $segments, 'text' ) );
		$this->assertNoSelfRouteBreadcrumbLink( $segments, PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_OVERVIEW );
	}

	public function test_activity_log_header_uses_normalized_terminal_leaf_title() :void {
		$payload = $this->renderRoute( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_LOGS );
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$this->assertHtmlContainsMarker( 'View Activity Logs', $html, 'Activity log header text marker' );

		$segments = $this->getHeaderSegments( $payload );
		$lastSegment = \end( $segments );
		$this->assertSame( 'View Activity Logs', \is_array( $lastSegment ) ? (string)( $lastSegment[ 'text' ] ?? '' ) : '' );
		$this->assertNoSelfRouteBreadcrumbLink( $segments, PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_LOGS );
	}

	public function test_traffic_log_header_uses_normalized_terminal_leaf_title() :void {
		$payload = $this->renderRoute( PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LOGS );
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$this->assertHtmlContainsMarker( 'View HTTP Request Logs', $html, 'Traffic log header text marker' );

		$segments = $this->getHeaderSegments( $payload );
		$lastSegment = \end( $segments );
		$this->assertSame( 'View HTTP Request Logs', \is_array( $lastSegment ) ? (string)( $lastSegment[ 'text' ] ?? '' ) : '' );
		$this->assertNoSelfRouteBreadcrumbLink( $segments, PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LOGS );
	}

	public function test_live_traffic_log_header_uses_normalized_terminal_leaf_title() :void {
		$payload = $this->renderRoute( PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LIVE );
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$this->assertHtmlContainsMarker( 'View Live HTTP Logs', $html, 'Live traffic log header text marker' );

		$segments = $this->getHeaderSegments( $payload );
		$lastSegment = \end( $segments );
		$this->assertSame( 'View Live HTTP Logs', \is_array( $lastSegment ) ? (string)( $lastSegment[ 'text' ] ?? '' ) : '' );
		$this->assertNoSelfRouteBreadcrumbLink( $segments, PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LIVE );
	}
}
