<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\Render\PageAdminPlugin,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class OperatorBreadcrumbHeaderIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	private function processor() :ActionProcessor {
		return new ActionProcessor();
	}

	private function renderRoute( string $nav, string $subNav ) :array {
		$filter = self::con()->prefix( 'bypass_is_plugin_admin' );
		add_filter( $filter, '__return_true', 1000 );

		try {
			return $this->processor()
						->processAction( PageAdminPlugin::SLUG, [
							Constants::NAV_ID     => $nav,
							Constants::NAV_SUB_ID => $subNav,
						] )
						->payload();
		}
		finally {
			remove_filter( $filter, '__return_true', 1000 );
		}
	}

	private function extractHeaderTitleText( string $html ) :string {
		$header = $this->extractHeaderContainer( $html );
		$plain = \preg_replace( '/\s+/', ' ', \trim( \strip_tags( $header ) ) );
		return \is_string( $plain ) ? $plain : '';
	}

	private function extractHeaderContainer( string $html ) :string {
		$decodedHtml = $this->decodeHtmlEntities( $html );
		if ( \preg_match( '#<h4[^>]*inner-page-header-title[^>]*>(.*?)</h4>#is', $decodedHtml, $matches ) === 1 ) {
			return (string)$matches[ 1 ];
		}
		return '';
	}

	private function assertNoSelfRouteBreadcrumbLink( string $html, string $nav, string $subNav ) :void {
		$header = $this->extractHeaderContainer( $html );
		$this->assertNotSame( '', $header, 'Expected inner page header container to be present.' );

		\preg_match_all( '#<a\b[^>]*href="([^"]+)"[^>]*>#i', $header, $hrefMatches );
		foreach ( $hrefMatches[ 1 ] ?? [] as $href ) {
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

		$header = $this->extractHeaderTitleText( $html );
		$this->assertDoesNotMatchRegularExpression( '#Investigate\s*[^A-Za-z0-9]+\s*Investigate#', $header );
		$this->assertNoSelfRouteBreadcrumbLink( $html, PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_OVERVIEW );
	}

	public function test_activity_log_header_uses_normalized_terminal_leaf_title() :void {
		$payload = $this->renderRoute( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_LOGS );
		$html = (string)( $payload[ 'render_output' ] ?? '' );

		$header = $this->extractHeaderTitleText( $html );
		$this->assertStringContainsString( 'View Activity Logs', $header );
		$this->assertNoSelfRouteBreadcrumbLink( $html, PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_LOGS );
	}

	public function test_traffic_log_header_uses_normalized_terminal_leaf_title() :void {
		$payload = $this->renderRoute( PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LOGS );
		$html = (string)( $payload[ 'render_output' ] ?? '' );

		$header = $this->extractHeaderTitleText( $html );
		$this->assertStringContainsString( 'View HTTP Request Logs', $header );
		$this->assertNoSelfRouteBreadcrumbLink( $html, PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LOGS );
	}

	public function test_live_traffic_log_header_uses_normalized_terminal_leaf_title() :void {
		$payload = $this->renderRoute( PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LIVE );
		$html = (string)( $payload[ 'render_output' ] ?? '' );

		$header = $this->extractHeaderTitleText( $html );
		$this->assertStringContainsString( 'View Live HTTP Logs', $header );
		$this->assertNoSelfRouteBreadcrumbLink( $html, PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LIVE );
	}
}

