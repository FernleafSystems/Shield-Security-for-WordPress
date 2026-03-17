<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\AjaxRender,
	Actions\Render\PluginAdminPages\{
		ConfigureDrillDownDiagnosis,
		ConfigureDrillDownEditor,
		PageConfigureLanding
	},
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	ModeLandingAssertions,
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ConfigureLandingPageIntegrationTest extends ShieldIntegrationTestCase {

	use ModeLandingAssertions;
	use PluginAdminRouteRenderAssertions;

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	private function renderConfigureLandingPage( array $params = [] ) :array {
		return $this->processActionPayloadWithAdminBypass( PageConfigureLanding::SLUG, \array_merge( [
			Constants::NAV_ID     => PluginNavs::NAV_ZONES,
			Constants::NAV_SUB_ID => PluginNavs::SUBNAV_ZONES_OVERVIEW,
		], $params ) );
	}

	public function test_landing_renders_posture_strip_drill_shell_and_context_card() :void {
		$payload = $this->renderConfigureLandingPage();
		$html = $this->assertRouteRenderOutputHealthy( $payload, 'configure landing' );
		$xpath = $this->createDomXPathFromHtml( $html );
		$vars = \is_array( $payload[ 'render_data' ][ 'vars' ] ?? null ) ? $payload[ 'render_data' ][ 'vars' ] : [];

		$this->assertModeShellPayload( $vars, 'configure', 'good', false );
		$this->assertArrayNotHasKey( 'zone_tiles', $vars );
		$this->assertArrayNotHasKey( 'configure_render_action', $vars );
		$this->assertSame( 3, \count( $vars[ 'drill_shell' ][ 'layers' ] ?? [] ) );
		$this->assertSame( 0, (int)( $vars[ 'drill_shell' ][ 'active_index' ] ?? -1 ) );
		$this->assertSame(
			ActionData::FIELD_SHIELD,
			$vars[ 'configure_ajax' ][ 'diagnosis_render_action' ][ ActionData::FIELD_ACTION ] ?? ''
		);
		$this->assertSame(
			AjaxRender::SLUG,
			$vars[ 'configure_ajax' ][ 'diagnosis_render_action' ][ ActionData::FIELD_EXECUTE ] ?? ''
		);
		$this->assertSame(
			ConfigureDrillDownDiagnosis::SLUG,
			$vars[ 'configure_ajax' ][ 'diagnosis_render_action' ][ 'render_slug' ] ?? ''
		);
		$this->assertSame(
			ConfigureDrillDownEditor::SLUG,
			$vars[ 'configure_ajax' ][ 'editor_render_action' ][ 'render_slug' ] ?? ''
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-section="posture-strip"]',
			'Configure landing should render the posture strip above the drill-down shell'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-section="drilldown"]//*[@data-drill-shell="1"]',
			'Configure landing should render the shared drill-down shell'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-drill-context-card="configure_drill_shell"]',
			'Configure landing should render the shared drill context card'
		);
		$this->assertXPathNotExists(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " shield-rail-sidebar ")]',
			'Configure landing should not render the old rail sidebar'
		);
	}

	public function test_valid_deep_link_starts_on_diagnosis_and_invalid_key_falls_back() :void {
		$validPayload = $this->renderConfigureLandingPage( [ 'zone' => 'login' ] );
		$invalidPayload = $this->renderConfigureLandingPage( [ 'zone' => 'login_protection' ] );

		$this->assertSame( 1, (int)( $validPayload[ 'render_data' ][ 'vars' ][ 'drill_shell' ][ 'active_index' ] ?? -1 ) );
		$this->assertNotSame(
			'',
			(string)( $validPayload[ 'render_data' ][ 'vars' ][ 'drill_shell' ][ 'layers' ][ 1 ][ 'body' ] ?? '' )
		);
		$this->assertSame( 0, (int)( $invalidPayload[ 'render_data' ][ 'vars' ][ 'drill_shell' ][ 'active_index' ] ?? -1 ) );
	}

	public function test_diagnosis_ajax_returns_html_context_and_optional_landing_refresh() :void {
		$payload = $this->processActionPayloadWithAdminBypass( ConfigureDrillDownDiagnosis::SLUG, [
			'zone' => 'login',
		] );
		$refreshPayload = $this->processActionPayloadWithAdminBypass( ConfigureDrillDownDiagnosis::SLUG, [
			'zone'                    => 'login',
			'include_landing_refresh' => 1,
		] );
		$html = (string)( $payload[ 'html' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertSame( 'login', (string)( $payload[ 'zone_selection' ][ 'key' ] ?? '' ) );
		$this->assertSame( 'login', (string)( $payload[ 'editor_selection' ][ 'key' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $payload[ 'strip_text' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $payload[ 'strip_badge' ] ?? '' ) );
		$this->assertNotEmpty( $payload[ 'context' ] ?? [] );
		$this->assertArrayNotHasKey( 'landing_refresh', $payload );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-diagnosis="1"]',
			'Diagnosis AJAX should render the diagnosis wrapper'
		);
		$this->assertXPathExists(
			$xpath,
			'//button[@data-drill-target="editor" and @data-drill-editor-selection]',
			'Diagnosis AJAX should render the editor drill CTA'
		);
		$this->assertNotSame( '', (string)( $refreshPayload[ 'landing_refresh' ][ 'posture_strip_html' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $refreshPayload[ 'landing_refresh' ][ 'zones_html' ] ?? '' ) );
	}

	public function test_editor_ajax_wraps_real_zone_editor_without_rail_markup() :void {
		$payload = $this->processActionPayloadWithAdminBypass( ConfigureDrillDownEditor::SLUG, [
			'zone' => 'login',
		] );
		$html = (string)( $payload[ 'html' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertSame( 'login', (string)( $payload[ 'editor_selection' ][ 'key' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $payload[ 'strip_text' ] ?? '' ) );
		$this->assertNotEmpty( $payload[ 'context' ] ?? [] );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-editor="1"]',
			'Editor AJAX should render the Configure editor wrapper'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-editor="1"]//*[@data-configure-expand-ajax="1"]',
			'Editor AJAX should preserve the expandable row placeholders'
		);
		$this->assertXPathNotExists(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " shield-rail-sidebar ")]',
			'Editor AJAX should not render the old rail markup'
		);
	}
}
