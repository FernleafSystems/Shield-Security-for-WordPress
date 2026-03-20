<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\AjaxRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ConfigureDrillDownDiagnosis;
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

	public function test_landing_renders_shared_operator_chrome_and_two_layer_drill_shell() :void {
		$payload = $this->renderConfigureLandingPage();
		$html = $this->assertRouteRenderOutputHealthy( $payload, 'configure landing' );
		$xpath = $this->createDomXPathFromHtml( $html );
		$vars = \is_array( $payload[ 'render_data' ][ 'vars' ] ?? null ) ? $payload[ 'render_data' ][ 'vars' ] : [];

		$this->assertModeShellPayload( $vars, 'configure', 'good', false );
		$this->assertArrayNotHasKey( 'zone_tiles', $vars );
		$this->assertArrayNotHasKey( 'configure_render_action', $vars );
		$this->assertSame( 2, \count( $vars[ 'drill_shell' ][ 'layers' ] ?? [] ) );
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
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-section="drilldown"]//*[@data-drill-shell="1"]',
			'Configure landing should render the shared drill-down shell'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-drill-layer-key="zones" and string-length(@data-drill-layer-header) > 0]',
			'Configure landing should render producer-owned layer header JSON for the zones layer'
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
			'//*[@data-configure-diagnosis="1"]//*[contains(concat(" ", normalize-space(@class), " "), " configure-diagnosis__next-move ")]',
			'Deep-linked diagnosis should render the next move guidance block'
		);
		$this->assertXPathNotExists(
			$validXpath,
			'//*[@data-configure-diagnosis="1"]//*[contains(concat(" ", normalize-space(@class), " "), " configure-diagnosis__settings-link ")]',
			'Deep-linked diagnosis should not render the removed settings page link'
		);
		$this->assertXPathExists(
			$validXpath,
			'//*[@data-configure-diagnosis="1"]//*[@data-configure-expand-ajax="1"][@data-zone_component_slug][@data-zone_component_action]',
			'Deep-linked diagnosis should render reusable expansion placeholders with component action data'
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
		$this->assertSame( 'Login', (string)( $payload[ 'header' ][ 'title' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $payload[ 'header' ][ 'badge' ] ?? '' ) );
		$this->assertArrayNotHasKey( 'editor_selection', $payload );
		$this->assertArrayNotHasKey( 'landing_refresh', $payload );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-diagnosis="1"]',
			'Diagnosis AJAX should render the diagnosis wrapper'
		);
		$this->assertXPathNotExists(
			$xpath,
			'//*[@data-configure-diagnosis="1"]//*[contains(concat(" ", normalize-space(@class), " "), " configure-diagnosis__settings-link ")]',
			'Diagnosis AJAX should not render the removed settings link'
		);
		$this->assertGreaterThan(
			0,
			$xpath->query( '//*[@data-configure-diagnosis="1"]//*[@data-shield-expand-trigger="1"]' )->length,
			'Diagnosis AJAX should render shared expandable detail rows for configurable findings'
		);
		$this->assertNotSame( '', (string)( $refreshPayload[ 'landing_refresh' ][ 'root_step_json' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $refreshPayload[ 'landing_refresh' ][ 'zones_html' ] ?? '' ) );
	}

	public function test_scans_and_spam_diagnosis_render_scoped_rows_and_general_settings() :void {
		$scansPayload = $this->processActionPayloadWithAdminBypass( ConfigureDrillDownDiagnosis::SLUG, [
			'zone' => 'scans',
		] );
		$spamPayload = $this->processActionPayloadWithAdminBypass( ConfigureDrillDownDiagnosis::SLUG, [
			'zone' => 'spam',
		] );

		$scansXpath = $this->createDomXPathFromHtml( (string)( $scansPayload[ 'html' ] ?? '' ) );
		$spamXpath = $this->createDomXPathFromHtml( (string)( $spamPayload[ 'html' ] ?? '' ) );

		$this->assertXPathExists(
			$scansXpath,
			'//*[@data-configure-diagnosis="1"]//*[@data-zone_component_slug="scan_scheduling"][@data-config_item="scan_frequency"]',
			'Scans diagnosis should scope scan scheduling to the dedicated callout'
		);
		$this->assertXPathExists(
			$scansXpath,
			'//*[@data-configure-diagnosis="1"]//*[@data-option_keys="ptg_reinstall_links"][@data-zone_component_slug="module_scans"]',
			'Scans diagnosis should expose leftover scan options through a General settings row'
		);
		$this->assertXPathExists(
			$spamXpath,
			'//*[@data-configure-diagnosis="1"]//*[@data-zone_component_slug="trusted_commenters"][@data-config_item="trusted_commenter_minimum"]',
			'SPAM diagnosis should scope trusted commenters to the dedicated callout'
		);
		$this->assertXPathExists(
			$spamXpath,
			'//*[@data-configure-diagnosis="1"]//*[@data-option_keys="comments_cooldown"][@data-zone_component_slug="module_spam"]',
			'SPAM diagnosis should expose leftover spam options through a General settings row'
		);
	}

	public function test_login_and_ips_diagnosis_surface_existing_hidden_callouts() :void {
		$loginPayload = $this->processActionPayloadWithAdminBypass( ConfigureDrillDownDiagnosis::SLUG, [
			'zone' => 'login',
		] );
		$ipsPayload = $this->processActionPayloadWithAdminBypass( ConfigureDrillDownDiagnosis::SLUG, [
			'zone' => 'ips',
		] );

		$loginXpath = $this->createDomXPathFromHtml( (string)( $loginPayload[ 'html' ] ?? '' ) );
		$ipsXpath = $this->createDomXPathFromHtml( (string)( $ipsPayload[ 'html' ] ?? '' ) );

		$this->assertXPathExists(
			$loginXpath,
			'//*[@data-configure-diagnosis="1"]//*[@data-zone_component_slug="login_hide"]',
			'Login diagnosis should surface the existing login-hide callout'
		);
		$this->assertXPathExists(
			$ipsXpath,
			'//*[@data-configure-diagnosis="1"]//*[@data-zone_component_slug="ip_blocking_rules"]',
			'IPs diagnosis should surface the existing IP blocking rules callout'
		);
	}
}
