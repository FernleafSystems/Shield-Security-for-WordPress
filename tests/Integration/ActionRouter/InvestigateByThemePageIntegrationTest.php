<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	Actions\Render\PluginAdminPages\InvestigateByThemePanelBody,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class InvestigateByThemePageIntegrationTest extends ShieldIntegrationTestCase {

	use PluginAdminRouteRenderAssertions;

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	private function renderByThemePage( string $themeSlug = '' ) :array {
		return $this->renderPluginAdminRoutePayload(
			PluginNavs::NAV_ACTIVITY,
			PluginNavs::SUBNAV_ACTIVITY_BY_THEME,
			$themeSlug !== '' ? [ 'theme_slug' => $themeSlug ] : []
		);
	}

	private function renderByThemePanelBody( string $themeSlug = '' ) :array {
		$params = [
			Constants::NAV_ID     => PluginNavs::NAV_ACTIVITY,
			Constants::NAV_SUB_ID => PluginNavs::SUBNAV_ACTIVITY_BY_THEME,
		];
		if ( $themeSlug !== '' ) {
			$params[ 'theme_slug' ] = $themeSlug;
		}
		return $this->processActionPayloadWithAdminBypass( InvestigateByThemePanelBody::SLUG, $params );
	}

	public function test_valid_lookup_renders_file_status_and_activity_tables() :void {
		$themeSlug = $this->firstInstalledThemeSlug();
		$renderData = (array)( $this->renderByThemePanelBody( $themeSlug )[ 'render_data' ] ?? [] );
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
			$this->assertSame( 'theme', (string)( $tables[ 'activity' ][ 'subject_type' ] ?? '' ) );
			$this->assertSame( $themeSlug, (string)( $tables[ 'activity' ][ 'subject_id' ] ?? '' ) );
		}
		if ( $fileStatusCount > 0 ) {
			$tableAction = \json_decode( (string)( $tables[ 'file_status' ][ 'table_action_attr' ] ?? '' ), true, 512, \JSON_THROW_ON_ERROR );
			$this->assertSame( 'theme', $tableAction[ 'type' ] ?? '' );
			$this->assertSame( $themeSlug, $tableAction[ 'file' ] ?? '' );
		}
		$this->assertArrayHasKey( 'vulnerabilities', $vars );

		$routePayload = $this->renderByThemePage( $themeSlug );
		$this->assertRouteRenderOutputHealthy( $routePayload, 'activity by-theme route' );
		$this->assertPluginAdminShellRouteState( $routePayload, PluginNavs::SUBNAV_ACTIVITY_BY_THEME );
	}

	public function test_no_lookup_route_preloads_theme_panel_lookup_form() :void {
		$routePayload = $this->renderByThemePage();
		$this->assertRouteRenderOutputHealthy( $routePayload, 'activity by-theme route without lookup' );
		$this->assertPluginAdminShellRouteState( $routePayload, PluginNavs::SUBNAV_ACTIVITY_BY_THEME );
	}

	public function test_lookup_form_includes_route_preservation_contract() :void {
		$renderData = (array)( $this->renderByThemePanelBody()[ 'render_data' ] ?? [] );
		$this->assertSame( PluginNavs::SUBNAV_ACTIVITY_BY_THEME, (string)( $renderData[ 'vars' ][ 'lookup_route' ][ Constants::NAV_SUB_ID ] ?? '' ) );
		$this->assertSame( 'shield-investigate-theme-lookup-theme_slug-control', (string)( $renderData[ 'vars' ][ 'lookup_field' ][ 'control_id' ] ?? '' ) );
	}

	public function test_panel_body_action_renders_theme_markup_contract() :void {
		$themeSlug = $this->firstInstalledThemeSlug();

		$payload = $this->renderByThemePanelBody( $themeSlug );
		$this->assertRouteRenderOutputHealthy(
			$payload,
			'investigate by-theme panel body action'
		);
		$renderData = (array)( $payload[ 'render_data' ] ?? [] );
		$this->assertSame( '/wpadmin/components/investigate/theme_body.twig', (string)( $payload[ 'render_template' ] ?? '' ) );
		$this->assertSame( true, $renderData[ 'flags' ][ 'has_subject' ] ?? null );
		$this->assertSame( $themeSlug, (string)( $renderData[ 'vars' ][ 'theme_slug' ] ?? '' ) );
		$this->assertArrayHasKey( 'context_step_json', (array)( $renderData[ 'vars' ][ 'subject_header' ] ?? [] ) );
	}

	private function firstInstalledThemeSlug() :string {
		$themes = Services::WpThemes()->getInstalledStylesheets();
		if ( empty( $themes ) ) {
			$this->markTestSkipped( 'No installed themes were available for investigate-by-theme integration test.' );
		}
		return (string)\array_values( $themes )[ 0 ];
	}
}
