<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	ConfigureGoalPanels,
	PageConfigureLanding
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\PluginAdminRouteRenderAssertions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

/**
 * Links that name a component or a setting open Configure on the topic panel that holds it.
 */
class ConfigurationDeepLinkIntegrationTest extends ShieldIntegrationTestCase {

	use PluginAdminRouteRenderAssertions;

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	public function test_component_link_opens_configure_focused_on_its_topic() :void {
		$query = $this->queryOf( $this->requireController()->plugin_urls->cfgForZoneComponent( 'two_factor_auth' ) );
		$this->assertSame( PluginNavs::NAV_ZONES, $query[ 'nav' ] );

		$focus = $this->focusRequestFor( $query );
		$this->assertSame( $query[ 'row_key' ], $focus[ 'row_key' ] ?? null );
	}

	public function test_setting_link_opens_configure_focused_on_that_setting() :void {
		$query = $this->queryOf( $this->requireController()->plugin_urls->cfgForOpt( 'antibot_high_reputation_minimum' ) );
		$focus = $this->focusRequestFor( $query );
		$this->assertSame( 'antibot_high_reputation_minimum', $focus[ 'config_item' ] ?? null );
		$this->assertContains( 'antibot_high_reputation_minimum', ConfigureGoalPanels::topicOptionKeys( $query[ 'zone' ], $focus[ 'row_key' ] ) );
	}

	public function test_component_link_keeps_only_a_setting_of_that_component() :void {
		$urls = $this->requireController()->plugin_urls;
		$componentLink = $urls->cfgForZoneComponent( 'two_factor_auth' );

		$this->assertSame( $componentLink, $urls->legacyAdminRouteRedirect( PluginNavs::NAV_ZONES, PluginNavs::SUBNAV_ZONES_OVERVIEW, [
			'component'   => 'two_factor_auth',
			'config_item' => 'antibot_high_reputation_minimum',
		] ) );

		$own = \array_values( \array_filter(
			$this->requireController()->comps->zones->getZoneComponent( 'two_factor_auth' )->getOptions(),
			static fn( string $key ) :bool => ConfigureGoalPanels::topicForOption( $key ) !== null
		) );
		$this->assertNotEmpty( $own );
		$focused = $urls->legacyAdminRouteRedirect( PluginNavs::NAV_ZONES, PluginNavs::SUBNAV_ZONES_OVERVIEW, [
			'component'   => 'two_factor_auth',
			'config_item' => \end( $own ),
		] );
		$this->assertSame( $urls->cfgForOpt( \end( $own ) ), $focused );
	}

	public function test_unknown_or_malformed_components_do_not_redirect() :void {
		$urls = $this->requireController()->plugin_urls;
		foreach ( [ 'unknown_component', 'whitelabel,module_integrations', [ 'whitelabel' ] ] as $slug ) {
			$this->assertNull( $urls->legacyAdminRouteRedirect( PluginNavs::NAV_ZONES, PluginNavs::SUBNAV_ZONES_OVERVIEW, [ 'component' => $slug ] ) );
		}
		// A slug is reduced to its key characters, as every admin route is, so only a known component remains.
		$this->assertSame(
			$urls->cfgForZoneComponent( 'whitelabel' ),
			$urls->legacyAdminRouteRedirect( PluginNavs::NAV_ZONES, PluginNavs::SUBNAV_ZONES_OVERVIEW, [ 'component' => '../whitelabel' ] )
		);
	}

	public function test_configure_page_ignores_a_leftover_component_parameter() :void {
		$payload = $this->processActionPayloadWithAdminBypass( PageConfigureLanding::SLUG, [
			Constants::NAV_ID     => PluginNavs::NAV_ZONES,
			Constants::NAV_SUB_ID => PluginNavs::SUBNAV_ZONES_OVERVIEW,
			'component'           => 'two_factor_auth',
		] );
		$this->assertRouteRenderOutputHealthy( $payload, 'configure with component parameter' );
		$this->assertArrayNotHasKey( 'configure_component_action_json', $payload[ 'render_data' ][ 'vars' ] );
	}

	private function queryOf( string $url ) :array {
		\parse_str( (string)\parse_url( $url, \PHP_URL_QUERY ), $query );
		return $query;
	}

	private function focusRequestFor( array $query ) :array {
		$payload = $this->processActionPayloadWithAdminBypass( PageConfigureLanding::SLUG, \array_merge( $query, [
			Constants::NAV_ID     => $query[ 'nav' ],
			Constants::NAV_SUB_ID => $query[ 'nav_sub' ],
		] ) );
		$this->assertRouteRenderOutputHealthy( $payload, 'configure deep link' );
		$focus = \json_decode( (string)( $payload[ 'render_data' ][ 'vars' ][ 'configure_focus_request_json' ] ?? '' ), true );
		return \is_array( $focus ) ? $focus : [];
	}
}
