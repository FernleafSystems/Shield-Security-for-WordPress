<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\AjaxRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ConfigureDrillDownDiagnosis;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ConfigureDrillDownEditor;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageConfigureLanding;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\ModeLandingAssertions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\PluginAdminRouteRenderAssertions;
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
			'//*[@data-configure-landing="1"][@data-configure-inline-save-action]',
			'Configure landing should expose the inline save action on the root wrapper'
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
		$validHtml = $this->assertRouteRenderOutputHealthy( $validPayload, 'configure landing deep link' );
		$validXpath = $this->createDomXPathFromHtml( $validHtml );

		$this->assertSame( 1, (int)( $validPayload[ 'render_data' ][ 'vars' ][ 'drill_shell' ][ 'active_index' ] ?? -1 ) );
		$this->assertNotSame(
			'',
			(string)( $validPayload[ 'render_data' ][ 'vars' ][ 'drill_shell' ][ 'layers' ][ 1 ][ 'body' ] ?? '' )
		);
		$this->assertXPathExists(
			$validXpath,
			'//*[@data-configure-diagnosis="1"]//*[contains(concat(" ", normalize-space(@class), " "), " zone-summary-header ")]',
			'Deep-linked diagnosis should include the new zone summary header'
		);
		$this->assertXPathExists(
			$validXpath,
			'//*[@data-configure-diagnosis="1"]//*[contains(concat(" ", normalize-space(@class), " "), " configure-diagnosis__next-move ")]',
			'Deep-linked diagnosis should render the next move guidance block'
		);
		$this->assertXPathExists(
			$validXpath,
			'//*[@data-configure-diagnosis="1"]//*[contains(concat(" ", normalize-space(@class), " "), " configure-diagnosis__settings-link ")]',
			'Deep-linked diagnosis should render the settings page link in the next move block'
		);
		if ( $validXpath->query( '//*[@data-configure-diagnosis="1"]//*[@data-configure-healthy-settings-toggle="1"]' )->length > 0 ) {
			$this->assertXPathExists(
				$validXpath,
				'//*[@data-configure-diagnosis="1"]//*[contains(concat(" ", normalize-space(@class), " "), " healthy-settings-header ")]',
				'Deep-linked diagnosis should render healthy settings toggle when healthy rows exist'
			);
			$this->assertXPathExists(
				$validXpath,
				'//*[@data-configure-diagnosis="1"]//*[contains(concat(" ", normalize-space(@class), " "), " healthy-settings-body ")]',
				'Deep-linked diagnosis should render healthy settings body when healthy rows exist'
			);
			$healthySettingRows = $validXpath->query(
				'//*[@data-configure-diagnosis="1"]//*[contains(concat(" ", normalize-space(@class), " "), "healthy-settings-body")]//*[contains(concat(" ", normalize-space(@class), " "), "setting-card ")]'
			);
			$this->assertGreaterThan( 0, $healthySettingRows->length, 'Deep-linked diagnosis should include healthy setting cards when healthy rows exist' );
		}
		$this->assertXPathExists(
			$validXpath,
			'//*[@data-configure-diagnosis="1"]//*[@data-configure-expand-ajax="1"][@data-zone_component_slug][@data-zone_component_action]',
			'Deep-linked diagnosis should render reusable expansion placeholders with component action data'
		);
		$this->assertXPathNotExists(
			$validXpath,
			'//*[@data-configure-diagnosis="1"]//button[@data-drill-target="editor"]',
			'Deep-linked diagnosis should not render the removed editor CTA'
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
		$this->assertSame( 'Login', (string)( $payload[ 'strip_text' ] ?? '' ) );
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
			'//*[@data-configure-diagnosis="1"]//*[contains(concat(" ", normalize-space(@class), " "), " zone-summary-header ")]',
			'Diagnosis AJAX should render the new zone summary header'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-diagnosis="1"]//*[contains(concat(" ", normalize-space(@class), " "), " configure-diagnosis__next-move ")]',
			'Diagnosis AJAX should render the next move block'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-diagnosis="1"]//*[contains(concat(" ", normalize-space(@class), " "), " configure-diagnosis__settings-link ")]',
			'Diagnosis AJAX should render the next move settings link'
		);
		$this->assertGreaterThan(
			0,
			$xpath->query( '//*[@data-configure-diagnosis="1"]//*[@data-configure-inline-toggle="1"] | //*[@data-configure-diagnosis="1"]//*[@data-configure-inline-select="1"]' )->length,
			'Diagnosis AJAX should render inline toggle or select controls for configurable findings'
		);
		if ( $xpath->query( '//*[@data-configure-diagnosis="1"]//*[@data-configure-healthy-settings-toggle="1"]' )->length > 0 ) {
			$this->assertXPathExists(
				$xpath,
				'//*[@data-configure-diagnosis="1"]//*[contains(concat(" ", normalize-space(@class), " "), " healthy-settings-header ")]',
				'Diagnosis AJAX should render healthy settings toggle when healthy rows exist'
			);
			$this->assertXPathExists(
				$xpath,
				'//*[@data-configure-diagnosis="1"]//*[contains(concat(" ", normalize-space(@class), " "), " healthy-settings-body ")]',
				'Diagnosis AJAX should render healthy settings body when healthy rows exist'
			);
		}
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-diagnosis="1"]//*[@data-configure-expand-ajax="1"][@data-zone_component_slug][@data-zone_component_action]',
			'Diagnosis AJAX should render reusable expansion placeholders with component action data'
		);
		$this->assertXPathNotExists(
			$xpath,
			'//*[@data-configure-diagnosis="1"]//button[@data-drill-target="editor"]',
			'Diagnosis AJAX should not render the removed editor drill CTA'
		);
		$this->assertNotSame( '', (string)( $refreshPayload[ 'landing_refresh' ][ 'posture_strip_html' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $refreshPayload[ 'landing_refresh' ][ 'zones_html' ] ?? '' ) );
	}

	public function test_general_diagnosis_ajax_renders_review_rows_without_healthy_state() :void {
		$payload = $this->processActionPayloadWithAdminBypass( ConfigureDrillDownDiagnosis::SLUG, [
			'zone' => 'general',
		] );
		$html = (string)( $payload[ 'html' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertSame( 'general', (string)( $payload[ 'zone_selection' ][ 'key' ] ?? '' ) );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-diagnosis="1"]//*[contains(concat(" ", normalize-space(@class), " "), " configure-diagnosis__review ")]',
			'General diagnosis should render the dedicated review section'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-diagnosis="1"]//*[contains(concat(" ", normalize-space(@class), " "), " configure-diagnosis__review ")]//*[contains(concat(" ", normalize-space(@class), " "), " setting-card__control-row ")]',
			'General diagnosis review rows should preserve the inline control contract'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-diagnosis="1"]//*[contains(concat(" ", normalize-space(@class), " "), " configure-diagnosis__review ")]//*[@data-configure-expand-ajax="1"][@data-zone_component_slug][@data-zone_component_action]',
			'General diagnosis review rows should render expansion placeholders with component action data'
		);
		$this->assertXPathNotExists(
			$xpath,
			'//*[@data-configure-diagnosis="1"]//*[contains(concat(" ", normalize-space(@class), " "), " healthy-settings-header ")]',
			'General diagnosis should not render the healthy settings heading for review-only rows'
		);
		$this->assertXPathNotExists(
			$xpath,
			'//*[@data-configure-diagnosis="1"]//*[contains(concat(" ", normalize-space(@class), " "), " setting-card--healthy ")]',
			'General diagnosis should not render healthy setting cards for review-only rows'
		);
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
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-editor="1"]//*[@data-configure-zone-settings]',
			'Editor AJAX should still render the zone settings CTA when not hidden inline'
		);
		$this->assertXPathNotExists(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " shield-rail-sidebar ")]',
			'Editor AJAX should not render the old rail markup'
		);
	}
}
