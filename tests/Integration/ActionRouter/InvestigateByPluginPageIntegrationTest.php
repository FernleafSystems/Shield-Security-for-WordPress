<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	Actions\Render\PluginAdminPages\InvestigateByPluginPanelBody,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class InvestigateByPluginPageIntegrationTest extends ShieldIntegrationTestCase {

	use PluginAdminRouteRenderAssertions;

	public function set_up() {
		parent::set_up();

		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	private function renderByPluginPage( string $pluginSlug = '' ) :array {
		return $this->renderPluginAdminRoutePayload(
			PluginNavs::NAV_ACTIVITY,
			PluginNavs::SUBNAV_ACTIVITY_BY_PLUGIN,
			$pluginSlug !== '' ? [ 'plugin_slug' => $pluginSlug ] : []
		);
	}

	private function renderByPluginPanelBody( string $pluginSlug = '' ) :array {
		$params = [
			Constants::NAV_ID     => PluginNavs::NAV_ACTIVITY,
			Constants::NAV_SUB_ID => PluginNavs::SUBNAV_ACTIVITY_BY_PLUGIN,
		];
		if ( $pluginSlug !== '' ) {
			$params[ 'plugin_slug' ] = $pluginSlug;
		}
		return $this->processActionPayloadWithAdminBypass( InvestigateByPluginPanelBody::SLUG, $params );
	}

	public function test_valid_lookup_renders_file_status_and_activity_tables() :void {
		$pluginSlug = $this->firstInstalledPluginSlug();
		$renderData = (array)( $this->renderByPluginPanelBody( $pluginSlug )[ 'render_data' ] ?? [] );
		$vars = (array)( $renderData[ 'vars' ] ?? [] );
		$tables = (array)( $vars[ 'tables' ] ?? [] );
		$this->assertSame( true, $renderData[ 'flags' ][ 'has_lookup' ] ?? null );
		$this->assertSame( true, $renderData[ 'flags' ][ 'has_subject' ] ?? null );
		$fileStatusCount = (int)( $renderData[ 'vars' ][ 'tabs' ][ 'file_status' ][ 'count' ] ?? 0 );
		$activityCount = (int)( $renderData[ 'vars' ][ 'tabs' ][ 'activity' ][ 'count' ] ?? 0 );
		$this->assertArrayNotHasKey( 'back_to_investigate', $renderData[ 'hrefs' ] ?? [] );
		$this->assertArrayNotHasKey( 'back_to_investigate', $renderData[ 'strings' ] ?? [] );
		$this->assertSame( $fileStatusCount > 0, isset( $tables[ 'file_status' ][ 'table_id' ] ) );
		$this->assertSame( $activityCount > 0, isset( $tables[ 'activity' ][ 'table_type' ] ) );
		if ( $activityCount > 0 ) {
			$this->assertSame( 'plugin', (string)( $tables[ 'activity' ][ 'subject_type' ] ?? '' ) );
			$this->assertSame( $pluginSlug, (string)( $tables[ 'activity' ][ 'subject_id' ] ?? '' ) );
		}
		if ( $fileStatusCount > 0 ) {
			$tableAction = \json_decode( (string)( $tables[ 'file_status' ][ 'table_action_attr' ] ?? '' ), true, 512, \JSON_THROW_ON_ERROR );
			$this->assertSame( 'plugin', $tableAction[ 'type' ] ?? '' );
			$this->assertSame( $pluginSlug, $tableAction[ 'file' ] ?? '' );
		}
		$this->assertArrayHasKey( 'vulnerabilities', $vars );

		$routePayload = $this->renderByPluginPage( $pluginSlug );
		$this->assertRouteRenderOutputHealthy( $routePayload, 'activity by-plugin route' );
		$this->assertPluginAdminShellRouteState( $routePayload, PluginNavs::SUBNAV_ACTIVITY_BY_PLUGIN );
	}

	public function test_no_lookup_route_preloads_plugin_panel_lookup_form() :void {
		$routePayload = $this->renderByPluginPage();
		$this->assertRouteRenderOutputHealthy( $routePayload, 'activity by-plugin route without lookup' );
		$this->assertPluginAdminShellRouteState( $routePayload, PluginNavs::SUBNAV_ACTIVITY_BY_PLUGIN );
	}

	public function test_lookup_form_includes_route_preservation_contract() :void {
		$renderData = (array)( $this->renderByPluginPanelBody()[ 'render_data' ] ?? [] );
		$this->assertSame( PluginNavs::SUBNAV_ACTIVITY_BY_PLUGIN, (string)( $renderData[ 'vars' ][ 'lookup_route' ][ Constants::NAV_SUB_ID ] ?? '' ) );
		$this->assertSame( 'shield-investigate-plugin-lookup-plugin_slug-control', (string)( $renderData[ 'vars' ][ 'lookup_field' ][ 'control_id' ] ?? '' ) );
	}

	public function test_panel_body_action_renders_plugin_markup_contract() :void {
		$pluginSlug = $this->firstInstalledPluginSlug();

		$payload = $this->renderByPluginPanelBody( $pluginSlug );
		$this->assertRouteRenderOutputHealthy(
			$payload,
			'investigate by-plugin panel body action'
		);
		$renderData = (array)( $payload[ 'render_data' ] ?? [] );
		$this->assertSame( '/wpadmin/components/investigate/plugin_body.twig', (string)( $payload[ 'render_template' ] ?? '' ) );
		$this->assertSame( true, $renderData[ 'flags' ][ 'has_subject' ] ?? null );
		$this->assertSame( $pluginSlug, (string)( $renderData[ 'vars' ][ 'plugin_slug' ] ?? '' ) );
		$this->assertArrayHasKey( 'context_step_json', (array)( $renderData[ 'vars' ][ 'subject_header' ] ?? [] ) );
	}

	private function firstInstalledPluginSlug() :string {
		$plugins = Services::WpPlugins()->getInstalledPluginFiles();
		if ( empty( $plugins ) ) {
			$this->markTestSkipped( 'No installed plugins were available for investigate-by-plugin integration test.' );
		}
		return (string)\array_values( $plugins )[ 0 ];
	}
}
