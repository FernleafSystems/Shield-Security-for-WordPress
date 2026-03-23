<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	Actions\Render\PluginAdminPages\InvestigateByPluginPanelBody,
	Actions\Render\PluginAdminPages\PageInvestigateByPlugin,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	LookupRouteFormAssertions,
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class InvestigateByPluginPageIntegrationTest extends ShieldIntegrationTestCase {

	use LookupRouteFormAssertions, PluginAdminRouteRenderAssertions;

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

	private function renderByPluginInnerPage( string $pluginSlug = '' ) :array {
		$params = [
			Constants::NAV_ID     => PluginNavs::NAV_ACTIVITY,
			Constants::NAV_SUB_ID => PluginNavs::SUBNAV_ACTIVITY_BY_PLUGIN,
		];
		if ( $pluginSlug !== '' ) {
			$params[ 'plugin_slug' ] = $pluginSlug;
		}
		return $this->processActionPayloadWithAdminBypass( PageInvestigateByPlugin::SLUG, $params );
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
		$renderData = (array)( $this->renderByPluginInnerPage( $pluginSlug )[ 'render_data' ] ?? [] );
		$vars = (array)( $renderData[ 'vars' ] ?? [] );
		$tables = (array)( $vars[ 'tables' ] ?? [] );
		$this->assertSame( true, $renderData[ 'flags' ][ 'has_lookup' ] ?? null );
		$this->assertSame( true, $renderData[ 'flags' ][ 'has_subject' ] ?? null );
		$fileStatusCount = (int)( $renderData[ 'vars' ][ 'tabs' ][ 'file_status' ][ 'count' ] ?? 0 );
		$activityCount = (int)( $renderData[ 'vars' ][ 'tabs' ][ 'activity' ][ 'count' ] ?? 0 );
		$this->assertArrayNotHasKey( 'back_to_investigate', $renderData[ 'hrefs' ] ?? [] );
		$this->assertArrayNotHasKey( 'back_to_investigate', $renderData[ 'strings' ] ?? [] );
		$this->assertSame( 'plugin', (string)( $tables[ 'file_status' ][ 'subject_type' ] ?? '' ) );
		$this->assertSame( 'plugin', (string)( $tables[ 'activity' ][ 'subject_type' ] ?? '' ) );
		$this->assertSame( $pluginSlug, (string)( $tables[ 'file_status' ][ 'subject_id' ] ?? '' ) );
		$this->assertSame( $pluginSlug, (string)( $tables[ 'activity' ][ 'subject_id' ] ?? '' ) );
		$this->assertSame( $fileStatusCount > 0, isset( $tables[ 'file_status' ][ 'table_type' ] ) );
		$this->assertSame( $activityCount > 0, isset( $tables[ 'activity' ][ 'table_type' ] ) );
		$this->assertArrayHasKey( 'vulnerabilities', $vars );

		$payload = $this->renderByPluginPage( $pluginSlug );
		$this->assertRouteRenderOutputHealthy( $payload, 'legacy by-plugin route' );
		$routeVars = (array)( $payload[ 'render_data' ][ 'vars' ] ?? [] );
		$subjects = [];
		foreach ( (array)( $routeVars[ 'subjects' ] ?? [] ) as $subject ) {
			if ( \is_array( $subject ) && isset( $subject[ 'key' ] ) ) {
				$subjects[ (string)$subject[ 'key' ] ] = $subject;
			}
		}

		$this->assertSame( 'plugin', (string)( $routeVars[ 'mode_panel' ][ 'active_target' ] ?? '' ) );
		$this->assertTrue( (bool)( $routeVars[ 'mode_panel' ][ 'is_open' ] ?? false ) );
		$this->assertTrue( (bool)( $subjects[ 'plugin' ][ 'is_loaded' ] ?? false ) );
		$this->assertSame( $pluginSlug, (string)( $subjects[ 'plugin' ][ 'subject_title' ] ?? '' ) );
	}

	public function test_no_lookup_renders_without_investigation_tables() :void {
		$payload = $this->renderByPluginPage();
		$this->assertRouteRenderOutputHealthy( $payload, 'legacy by-plugin route without lookup' );
		$routeVars = (array)( $payload[ 'render_data' ][ 'vars' ] ?? [] );
		$subjects = [];
		foreach ( (array)( $routeVars[ 'subjects' ] ?? [] ) as $subject ) {
			if ( \is_array( $subject ) && isset( $subject[ 'key' ] ) ) {
				$subjects[ (string)$subject[ 'key' ] ] = $subject;
			}
		}

		$this->assertSame( 'plugin', (string)( $routeVars[ 'mode_panel' ][ 'active_target' ] ?? '' ) );
		$this->assertTrue( (bool)( $routeVars[ 'mode_panel' ][ 'is_open' ] ?? false ) );
		$this->assertFalse( (bool)( $subjects[ 'plugin' ][ 'is_loaded' ] ?? true ) );
	}

	public function test_lookup_form_includes_route_preservation_contract() :void {
		$payload = $this->renderByPluginPage();
		$html = (string)( $payload[ 'render_output' ] ?? '' );

		$form = $this->extractLookupFormForSubNav( $html, PluginNavs::SUBNAV_ACTIVITY_BY_PLUGIN );
		$this->assertLookupFormRouteContract( $form, PluginNavs::SUBNAV_ACTIVITY_BY_PLUGIN );
	}

	public function test_full_page_and_panel_body_actions_share_the_same_plugin_markup_contract() :void {
		$pluginSlug = $this->firstInstalledPluginSlug();

		$fullHtml = $this->assertRouteRenderOutputHealthy(
			$this->renderByPluginInnerPage( $pluginSlug ),
			'investigate by-plugin full page action'
		);
		$this->assertStringContainsString( 'data-inner-page-body-shell="1"', $fullHtml );
		$this->assertStringContainsString( 'data-investigate-subject-header="1"', $fullHtml );

		$panelHtml = $this->assertRouteRenderOutputHealthy(
			$this->renderByPluginPanelBody( $pluginSlug ),
			'investigate by-plugin panel body action'
		);
		$this->assertStringContainsString( 'data-investigate-subject-header="1"', $panelHtml );
		$this->assertStringContainsString( 'ShieldInvestigateByPluginTabsNav', $panelHtml );
		$this->assertStringNotContainsString( 'data-inner-page-body-shell="1"', $panelHtml );
	}

	private function firstInstalledPluginSlug() :string {
		$plugins = Services::WpPlugins()->getInstalledPluginFiles();
		if ( empty( $plugins ) ) {
			$this->markTestSkipped( 'No installed plugins were available for investigate-by-plugin integration test.' );
		}
		return (string)\array_values( $plugins )[ 0 ];
	}
}
