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

	private function renderConfigureDiagnosis( array $params = [] ) :array {
		return $this->processActionPayloadWithAdminBypass( ConfigureDrillDownDiagnosis::SLUG, $params );
	}

	public function test_landing_renders_shared_operator_chrome_and_two_layer_drill_shell() :void {
		$payload = $this->renderConfigureLandingPage();
		$this->assertRouteRenderOutputHealthy( $payload, 'configure landing' );
		$this->assertIsArray( $payload[ 'render_data' ][ 'vars' ] ?? null );
		$vars = $payload[ 'render_data' ][ 'vars' ];

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
		$this->assertNotSame( '', (string)( $payload[ 'render_output' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $vars[ 'drill_shell' ][ 'layers' ][ 0 ][ 'header' ][ 'title' ] ?? '' ) );
	}

	public function test_valid_deep_link_starts_on_diagnosis_and_invalid_key_falls_back() :void {
		$validPayload = $this->renderConfigureLandingPage( [ 'zone' => 'login' ] );
		$invalidPayload = $this->renderConfigureLandingPage( [ 'zone' => 'login_protection' ] );
		$this->assertRouteRenderOutputHealthy( $validPayload, 'configure landing deep link' );

		$this->assertSame( 1, (int)( $validPayload[ 'render_data' ][ 'vars' ][ 'drill_shell' ][ 'active_index' ] ?? -1 ) );
		$this->assertNotSame(
			'',
			(string)( $validPayload[ 'render_data' ][ 'vars' ][ 'drill_shell' ][ 'layers' ][ 1 ][ 'body' ] ?? '' )
		);
		$this->assertSame( 'Login', (string)( $validPayload[ 'render_data' ][ 'vars' ][ 'drill_shell' ][ 'layers' ][ 1 ][ 'header' ][ 'title' ] ?? '' ) );
		$this->assertSame( 0, (int)( $invalidPayload[ 'render_data' ][ 'vars' ][ 'drill_shell' ][ 'active_index' ] ?? -1 ) );
	}

	public function test_diagnosis_ajax_returns_html_context_and_optional_landing_refresh() :void {
		$payload = $this->renderConfigureDiagnosis( [
			'zone' => 'login',
		] );
		$refreshPayload = $this->renderConfigureDiagnosis( [
			'zone'                    => 'login',
			'include_landing_refresh' => 1,
		] );
		$this->assertIsArray( $payload[ 'render_data' ][ 'diagnosis' ] ?? null );
		$diagnosis = $payload[ 'render_data' ][ 'diagnosis' ];

		$this->assertSame( 'login', (string)( $payload[ 'zone_selection' ][ 'key' ] ?? '' ) );
		$this->assertSame( 'Login', (string)( $payload[ 'header' ][ 'title' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $payload[ 'html' ] ?? '' ) );
		$this->assertArrayNotHasKey( 'editor_selection', $payload );
		$this->assertArrayNotHasKey( 'landing_refresh', $payload );
		$this->assertArrayNotHasKey( 'settings_href', $diagnosis );
		$this->assertArrayNotHasKey( 'settings_label', $diagnosis );
		$this->assertGreaterThan( 0, \count( $this->allDiagnosisRows( $diagnosis ) ) );
		$this->assertNotSame( '', (string)( $refreshPayload[ 'landing_refresh' ][ 'root_step_json' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $refreshPayload[ 'landing_refresh' ][ 'zones_html' ] ?? '' ) );
	}

	public function test_scans_and_spam_diagnosis_render_scoped_rows_and_general_settings() :void {
		$scansPayload = $this->renderConfigureDiagnosis( [
			'zone' => 'scans',
		] );
		$spamPayload = $this->renderConfigureDiagnosis( [
			'zone' => 'spam',
		] );
		$this->assertIsArray( $scansPayload[ 'render_data' ][ 'diagnosis' ] ?? null );
		$this->assertIsArray( $spamPayload[ 'render_data' ][ 'diagnosis' ] ?? null );
		$scansDiagnosis = $scansPayload[ 'render_data' ][ 'diagnosis' ];
		$spamDiagnosis = $spamPayload[ 'render_data' ][ 'diagnosis' ];
		$scanScheduling = $this->findDiagnosisRowBySlug( $scansDiagnosis, 'scan_scheduling' );
		$scanGeneral = $this->findDiagnosisRowByOptionKeys( $scansDiagnosis, 'ptg_reinstall_links' );
		$trustedCommenters = $this->findDiagnosisRowBySlug( $spamDiagnosis, 'trusted_commenters' );
		$spamGeneral = $this->findDiagnosisRowByOptionKeys( $spamDiagnosis, 'comments_cooldown' );

		$this->assertSame( 'scan_frequency', (string)( $scanScheduling[ 'expand_action' ][ 'data_attributes' ][ 'config_item' ] ?? '' ) );
		$this->assertSame( 'module_scans', (string)( $scanGeneral[ 'expand_action' ][ 'data_attributes' ][ 'zone_component_slug' ] ?? '' ) );
		$this->assertSame( 'trusted_commenter_minimum', (string)( $trustedCommenters[ 'expand_action' ][ 'data_attributes' ][ 'config_item' ] ?? '' ) );
		$this->assertSame( 'module_spam', (string)( $spamGeneral[ 'expand_action' ][ 'data_attributes' ][ 'zone_component_slug' ] ?? '' ) );
	}

	public function test_login_and_ips_diagnosis_surface_existing_hidden_callouts() :void {
		$loginPayload = $this->renderConfigureDiagnosis( [
			'zone' => 'login',
		] );
		$ipsPayload = $this->renderConfigureDiagnosis( [
			'zone' => 'ips',
		] );
		$this->assertIsArray( $loginPayload[ 'render_data' ][ 'diagnosis' ] ?? null );
		$this->assertIsArray( $ipsPayload[ 'render_data' ][ 'diagnosis' ] ?? null );
		$loginDiagnosis = $loginPayload[ 'render_data' ][ 'diagnosis' ];
		$ipsDiagnosis = $ipsPayload[ 'render_data' ][ 'diagnosis' ];

		$this->assertNotEmpty( $this->findDiagnosisRowBySlug( $loginDiagnosis, 'login_hide' ) );
		$this->assertNotEmpty( $this->findDiagnosisRowBySlug( $ipsDiagnosis, 'ip_blocking_rules' ) );
	}

	/**
	 * @param array<string,mixed> $diagnosis
	 * @return list<array<string,mixed>>
	 */
	private function allDiagnosisRows( array $diagnosis ) :array {
		return \array_merge(
			$diagnosis[ 'problem_rows' ] ?? [],
			$diagnosis[ 'review_rows' ] ?? [],
			$diagnosis[ 'healthy_rows' ] ?? []
		);
	}

	/**
	 * @param array<string,mixed> $diagnosis
	 * @return array<string,mixed>|array{}
	 */
	private function findDiagnosisRowBySlug( array $diagnosis, string $zoneComponentSlug ) :array {
		foreach ( $this->allDiagnosisRows( $diagnosis ) as $row ) {
			if ( (string)( $row[ 'expand_action' ][ 'data_attributes' ][ 'zone_component_slug' ] ?? '' ) === $zoneComponentSlug ) {
				return $row;
			}
		}
		return [];
	}

	/**
	 * @param array<string,mixed> $diagnosis
	 * @return array<string,mixed>|array{}
	 */
	private function findDiagnosisRowByOptionKeys( array $diagnosis, string $optionKeys ) :array {
		foreach ( $this->allDiagnosisRows( $diagnosis ) as $row ) {
			if ( (string)( $row[ 'expand_action' ][ 'data_attributes' ][ 'option_keys' ] ?? '' ) === $optionKeys ) {
				return $row;
			}
		}
		return [];
	}
}
