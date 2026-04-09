<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\AjaxRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ConfigureDrillDownDiagnosis;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ConfigureSearchResults;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ConfigureZoneTilesBuilder;
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

	private function renderConfigureSearchResults( array $params = [] ) :array {
		return $this->processActionPayloadWithAdminBypass( ConfigureSearchResults::SLUG, $params );
	}

	public function test_landing_renders_shared_operator_chrome_and_two_layer_drill_shell() :void {
		$payload = $this->renderConfigureLandingPage();
		$this->assertRouteRenderOutputHealthy( $payload, 'configure landing' );
		$this->assertIsArray( $payload[ 'render_data' ][ 'vars' ] ?? null );
		$vars = $payload[ 'render_data' ][ 'vars' ];
		$diagnosisAction = \json_decode( (string)( $vars[ 'configure_ajax' ][ 'diagnosis_render_action_json' ] ?? '' ), true );
		$xpath = $this->createDomXPathFromHtml( (string)( $payload[ 'render_output' ] ?? '' ) );

		$this->assertModeShellPayload( $vars, 'configure', 'configure', false );
		$this->assertArrayNotHasKey( 'zone_tiles', $vars );
		$this->assertArrayNotHasKey( 'configure_render_action', $vars );
		$this->assertSame( 2, \count( $vars[ 'drill_shell' ][ 'layers' ] ?? [] ) );
		$this->assertSame( 0, (int)( $vars[ 'drill_shell' ][ 'active_index' ] ?? -1 ) );
		$this->assertSame(
			ActionData::FIELD_SHIELD,
			$diagnosisAction[ ActionData::FIELD_ACTION ] ?? ''
		);
		$this->assertSame(
			AjaxRender::SLUG,
			$diagnosisAction[ ActionData::FIELD_EXECUTE ] ?? ''
		);
		$this->assertSame(
			ConfigureDrillDownDiagnosis::SLUG,
			$diagnosisAction[ 'render_slug' ] ?? ''
		);
		$this->assertNotSame( '', (string)( $payload[ 'render_output' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $vars[ 'drill_shell' ][ 'layers' ][ 0 ][ 'header' ][ 'title' ] ?? '' ) );
		$this->assertXPathCount(
			$xpath,
			'//*[@data-healthy-disclosure-toggle="1" or @data-healthy-disclosure-body="1"]',
			0,
			'Configure landing should not render the shared healthy disclosure wrapper'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-landing="1"]//*[@data-drill-target="diagnosis"]',
			'Configure landing should render zone diagnosis buttons directly in the landing grid'
		);
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

	public function test_landing_search_dock_exposes_search_action_and_normalized_focus_payload() :void {
		$payload = $this->renderConfigureLandingPage( [
			'zone'        => 'spam',
			'row_key'     => 'general_settings',
			'config_item' => 'comments_cooldown',
		] );
		$this->assertRouteRenderOutputHealthy( $payload, 'configure landing search dock' );

		$vars = $payload[ 'render_data' ][ 'vars' ] ?? [];
		$searchAction = \json_decode( (string)( $vars[ 'configure_ajax' ][ 'search_render_action_json' ] ?? '' ), true );
		$xpath = $this->createDomXPathFromHtml( (string)( $payload[ 'render_output' ] ?? '' ) );

		$this->assertSame(
			ConfigureSearchResults::SLUG,
			$searchAction[ 'render_slug' ] ?? ''
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-landing="1"]//*[@data-configure-search-input="1"]',
			'Configure landing should render the search dock input'
		);
		$this->assertSame(
			[
				'row_key'     => 'general_settings',
				'config_item' => 'comments_cooldown',
			],
			\json_decode( (string)( $vars[ 'configure_focus_request_json' ] ?? '' ), true )
		);
	}

	public function test_diagnosis_ajax_returns_html_context_and_optional_landing_refresh() :void {
		$payload = $this->renderConfigureDiagnosis( [
			'zone' => 'login',
		] );
		$refreshPayload = $this->renderConfigureDiagnosis( [
			'zone'                    => 'login',
			'include_landing_refresh' => 1,
		] );
		$xpath = $this->createDomXPathFromHtml( (string)( $payload[ 'html' ] ?? '' ) );

		$this->assertSame( 'login', (string)( $payload[ 'zone_selection' ][ 'key' ] ?? '' ) );
		$this->assertSame( 'Login', (string)( $payload[ 'header' ][ 'title' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $payload[ 'header' ][ 'next_step' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $payload[ 'html' ] ?? '' ) );
		$this->assertArrayNotHasKey( 'diagnosis', $payload );
		$this->assertArrayNotHasKey( 'render_data', $payload );
		$this->assertArrayNotHasKey( 'render_output', $payload );
		$this->assertArrayNotHasKey( 'editor_selection', $payload );
		$this->assertArrayNotHasKey( 'landing_refresh', $payload );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-diagnosis="1" and @data-configure-zone="login"]',
			'Diagnosis AJAX should render the selected configure diagnosis container'
		);
		$this->assertGreaterThan(
			0,
			$xpath->query( '//*[@data-configure-diagnosis="1"]//*[contains(concat(" ", normalize-space(@class), " "), " shield-detail-item ")]' )->length
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-configure-diagnosis="1"]//*[contains(concat(" ", normalize-space(@class), " "), " configure-diagnosis__next-move ")]',
			0,
			'Diagnosis AJAX should not render the removed next-move block'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-healthy-disclosure-toggle="1" or @data-healthy-disclosure-body="1"]',
			0,
			'Configure diagnosis should not render the shared healthy disclosure wrapper'
		);
		$this->assertNotSame( '', (string)( $refreshPayload[ 'landing_refresh' ][ 'root_step_json' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $refreshPayload[ 'landing_refresh' ][ 'zones_html' ] ?? '' ) );
		$refreshXpath = $this->createDomXPathFromHtml( (string)( $refreshPayload[ 'landing_refresh' ][ 'zones_html' ] ?? '' ) );
		$this->assertXPathCount(
			$refreshXpath,
			'//*[@data-healthy-disclosure-toggle="1" or @data-healthy-disclosure-body="1"]',
			0,
			'Configure landing refresh should not reintroduce the shared healthy disclosure wrapper'
		);
	}

	public function test_search_render_returns_flat_option_and_zone_results_for_real_query() :void {
		$payload = $this->renderConfigureSearchResults( [
			'search' => 'silentcaptcha',
		] );
		$this->assertRouteRenderOutputHealthy( $payload, 'configure search results' );

		$xpath = $this->createDomXPathFromHtml( (string)( $payload[ 'render_output' ] ?? '' ) );

		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-search-results="1"]',
			'Configure search should render the flat results container'
		);
		$this->assertGreaterThan(
			0,
			$xpath->query( '//*[@data-configure-search-results="1"]//a[contains(@class, "configure-search-results__item")]' )->length
		);
		$this->assertGreaterThan(
			0,
			$xpath->query( '//*[@data-configure-search-results="1"]//*[contains(concat(" ", normalize-space(@class), " "), " configure-search-results__type--option ")]' )->length
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-search-results="1"]//a[contains(@href, "row_key=") and contains(@href, "config_item=")]',
			'Configure search option links should target the exact configure row key'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-configure-search-results="1"]//a[contains(@href, "expand_id=") or contains(@href, "zone_component_slug=") or contains(@href, "option_keys=")]',
			0,
			'Configure search option links should not depend on replaced deep-link contracts'
		);
	}

	public function test_scans_and_spam_diagnosis_render_scoped_rows_and_general_settings() :void {
		$scansPayload = $this->renderConfigureDiagnosis( [
			'zone' => 'scans',
		] );
		$spamPayload = $this->renderConfigureDiagnosis( [
			'zone' => 'spam',
		] );
		$scansXpath = $this->createDomXPathFromHtml( (string)( $scansPayload[ 'html' ] ?? '' ) );
		$spamXpath = $this->createDomXPathFromHtml( (string)( $spamPayload[ 'html' ] ?? '' ) );

		$this->assertXPathExists(
			$scansXpath,
			'//*[@data-configure-row-key="scan_scheduling"]//*[@data-shield-expand-body="1"]//*[@data-configure-expand-ajax="1" and @data-zone_component_slug="scan_scheduling" and @data-config_item="scan_frequency"]',
			'Scans diagnosis should expose the scan scheduling config expansion contract'
		);
		$this->assertXPathExists(
			$scansXpath,
			'//*[@data-configure-row-key="general_settings"]//*[@data-shield-expand-body="1"]//*[@data-configure-expand-ajax="1" and @data-zone_component_slug="module_scans" and @data-option_keys="ptg_reinstall_links"]',
			'Scans diagnosis should expose the general scans settings expansion contract'
		);
		$this->assertXPathExists(
			$spamXpath,
			'//*[@data-configure-row-key="trusted_commenters"]//*[@data-shield-expand-body="1"]//*[@data-configure-expand-ajax="1" and @data-zone_component_slug="trusted_commenters" and @data-config_item="trusted_commenter_minimum"]',
			'Spam diagnosis should expose the trusted commenters config expansion contract'
		);
		$this->assertXPathExists(
			$spamXpath,
			'//*[@data-configure-row-key="general_settings"]//*[@data-shield-expand-body="1"]//*[@data-configure-expand-ajax="1" and @data-zone_component_slug="module_spam" and @data-option_keys="comments_cooldown"]',
			'Spam diagnosis should expose the general spam settings expansion contract'
		);
	}

	public function test_login_and_ips_diagnosis_surface_existing_hidden_callouts() :void {
		$loginPayload = $this->renderConfigureDiagnosis( [
			'zone' => 'login',
		] );
		$ipsPayload = $this->renderConfigureDiagnosis( [
			'zone' => 'ips',
		] );
		$loginXpath = $this->createDomXPathFromHtml( (string)( $loginPayload[ 'html' ] ?? '' ) );
		$ipsXpath = $this->createDomXPathFromHtml( (string)( $ipsPayload[ 'html' ] ?? '' ) );

		$this->assertXPathExists(
			$loginXpath,
			'//*[@data-configure-row-key="login_hide"]//*[@data-shield-expand-body="1"]//*[@data-configure-expand-ajax="1" and @data-zone_component_slug="login_hide"]',
			'Login diagnosis should still surface the login-hide expansion row'
		);
		$this->assertXPathExists(
			$ipsXpath,
			'//*[@data-configure-row-key="ip_blocking_rules"]//*[@data-shield-expand-body="1"]//*[@data-configure-expand-ajax="1" and @data-zone_component_slug="ip_blocking_rules"]',
			'IPs diagnosis should still surface the IP blocking rules expansion row'
		);
	}

	public function test_secadmin_diagnosis_header_actions_only_when_security_admin_is_enabled() :void {
		$snapshot = $this->snapshotSelectedOptions( [
			'admin_access_key',
			'sec_admin_users',
		] );

		try {
			$con = $this->requireController();
			$con->opts
				->optSet( 'admin_access_key', \wp_hash_password( 'integration-pin-123' ) )
				->optSet( 'sec_admin_users', [ 'admin' ] )
				->store();

			$enabledPayload = $this->renderConfigureDiagnosis( [
				'zone' => 'secadmin',
			] );

			$con->opts
				->optSet( 'admin_access_key', '' )
				->optSet( 'sec_admin_users', [] )
				->store();

			$disabledPayload = $this->renderConfigureDiagnosis( [
				'zone' => 'secadmin',
			] );

			$this->assertCount( 1, $enabledPayload[ 'header' ][ 'actions' ] ?? [] );
			$this->assertSame(
				'Disable Security Admin',
				(string)( $enabledPayload[ 'header' ][ 'actions' ][ 0 ][ 'label' ] ?? '' )
			);
			$this->assertSame( [], $disabledPayload[ 'header' ][ 'actions' ] ?? [ 'unexpected' ] );
		}
		finally {
			$this->restoreSelectedOptions( $snapshot );
		}
	}

	public function test_users_tile_builder_data_no_longer_surfaces_default_admin_user() :void {
		$tiles = ( new ConfigureZoneTilesBuilder() )->build();
		$usersTiles = \array_values( \array_filter(
			$tiles,
			static fn( array $tile ) :bool => (string)( $tile[ 'key' ] ?? '' ) === 'users'
		) );

		$this->assertCount( 1, $usersTiles );
		$components = $usersTiles[ 0 ][ 'panel' ][ 'components' ] ?? [];

		$this->assertNotContains( 'Default Admin User', \array_column( $components, 'title' ) );
		foreach ( $components as $component ) {
			$this->assertNotSame( [], $component[ 'config_action' ] ?? [] );
		}
	}
}
