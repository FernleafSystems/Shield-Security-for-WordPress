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
		$this->assertSame(
			(string)( $validPayload[ 'render_data' ][ 'vars' ][ 'drill_shell' ][ 'layers' ][ 1 ][ 'header' ][ 'title' ] ?? '' ),
			(string)( $validPayload[ 'render_data' ][ 'vars' ][ 'drill_shell' ][ 'layers' ][ 1 ][ 'selection' ][ 'label' ] ?? '' )
		);
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
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-search-dock="1" and @data-configure-search-state="idle"]//*[@data-configure-search-body="1" and @aria-live="polite" and @aria-busy="false"]',
			'Configure landing should render an idle live-region search dock'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " configure-landing__search-hint ")]',
			0,
			'Configure landing should not render the removed helper paragraph'
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

	public function test_reports_alerts_diagnosis_returns_neutral_zone_contract() :void {
		$payload = $this->renderConfigureDiagnosis( [
			'zone' => 'reports_alerts',
		] );

		$this->assertArrayHasKey( 'zone_selection', $payload );
		$this->assertArrayHasKey( 'header', $payload );
		$this->assertIsArray( $payload[ 'zone_selection' ] );
		$this->assertIsArray( $payload[ 'header' ] );
		$this->assertSame( 'reports_alerts', $payload[ 'zone_selection' ][ 'key' ] );
		$this->assertSame( 'neutral', $payload[ 'zone_selection' ][ 'status' ] );
		$this->assertSame(
			$payload[ 'zone_selection' ][ 'label' ],
			$payload[ 'header' ][ 'title' ]
		);
		$this->assertSame( 'neutral', $payload[ 'header' ][ 'badge_status' ] );
		$this->assertNotSame( '', $payload[ 'header' ][ 'summary' ] );
		$this->assertNotSame( '', $payload[ 'header' ][ 'focus' ] );
		$this->assertNotSame( '', $payload[ 'html' ] );
		$this->assertDiagnosisRowScope( $payload, 'reporting', 'reporting', [
			'block_send_email_address',
			'frequency_alert',
			'frequency_info',
		] );
		$this->assertDiagnosisRowScope( $payload, 'instant_alerts', 'instant_alerts', [
			'instant_alert_admins',
			'instant_alert_admin_login',
		] );
	}

	public function test_search_render_returns_flat_option_and_zone_results_for_real_query() :void {
		$payload = $this->renderConfigureSearchResults( [
			'search' => 'silentcaptcha',
		] );
		$this->assertRouteRenderOutputHealthy( $payload, 'configure search results' );

		$results = (array)( $payload[ 'render_data' ][ 'vars' ][ 'results' ] ?? [] );
		$xpath = $this->createDomXPathFromHtml( (string)( $payload[ 'render_output' ] ?? '' ) );

		$this->assertNotSame( [], $results );
		$this->assertSame( [ 'zone', 'option' ], \array_slice( \array_column( $results, 'type' ), 0, 2 ) );
		$optionResult = $this->findConfigureOptionResultByConfigItem( $results, 'enable_silentcaptcha' )
						?? $this->findConfigureOptionResultByConfigItem( $results, 'custom_silentcaptcha_toggle' );

		$this->assertNotNull( $optionResult );
		$this->assertSame( 'option', $optionResult[ 'type' ] ?? '' );
		$this->assertSame(
			[
				'row_key'     => 'silentcaptcha_component',
				'config_item' => (string)( \json_decode( (string)( $optionResult[ 'focus_request_json' ] ?? '' ), true )[ 'config_item' ] ?? '' ),
			],
			\json_decode( (string)( $optionResult[ 'focus_request_json' ] ?? '' ), true )
		);
		$this->assertHrefQueryMatches( (string)( $optionResult[ 'href' ] ?? '' ), [
			'zone'        => 'spam',
			'row_key'     => 'silentcaptcha_component',
			'config_item' => (string)( \json_decode( (string)( $optionResult[ 'focus_request_json' ] ?? '' ), true )[ 'config_item' ] ?? '' ),
		] );

		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-search-results="1"]',
			'Configure search should render the flat results container'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-search-results="1"]//a[@data-configure-search-result="1" and @data-drill-zone-selection]',
			'Configure search results should expose in-page drill selection data'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-search-results="1"]//a[contains(@href, "row_key=") and contains(@href, "config_item=") and @data-configure-focus-request]',
			'Configure search option results should expose the in-page focus payload'
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

	public function test_search_render_matches_hyphenated_and_compact_dash_option_queries() :void {
		$cases = [
			[
				'search'      => 'in-plugin',
				'config_item' => 'enable_upgrade_admin_notice',
			],
			[
				'search'      => 'xml-rpc',
				'config_item' => 'disable_xmlrpc',
			],
			[
				'search'      => 'xmlrpc',
				'config_item' => 'disable_xmlrpc',
			],
		];

		foreach ( $cases as $case ) {
			$payload = $this->renderConfigureSearchResults( [
				'search' => $case[ 'search' ],
			] );
			$this->assertRouteRenderOutputHealthy( $payload, 'configure search results for '.$case[ 'search' ] );

			$result = $this->findConfigureOptionResultByConfigItem(
				(array)( $payload[ 'render_data' ][ 'vars' ][ 'results' ] ?? [] ),
				$case[ 'config_item' ]
			);

			$this->assertNotNull(
				$result,
				'Configure search should return the expected option result for '.$case[ 'search' ]
			);
			$this->assertSame( 'option', $result[ 'type' ] ?? '' );
			$this->assertNotSame( '', (string)( $result[ 'href' ] ?? '' ) );
		}
	}

	public function test_scans_and_spam_diagnosis_render_scoped_rows_and_general_settings() :void {
		$scansPayload = $this->renderConfigureDiagnosis( [
			'zone' => 'scans',
		] );
		$spamPayload = $this->renderConfigureDiagnosis( [
			'zone' => 'spam',
		] );

		$this->assertDiagnosisRowScope( $scansPayload, 'scan_scheduling', 'scan_scheduling', [], 'scan_frequency' );
		$this->assertDiagnosisRowScope( $spamPayload, 'trusted_commenters', 'trusted_commenters', [], 'trusted_commenter_minimum' );
		$this->assertDiagnosisRowScope( $spamPayload, 'general_settings', 'module_spam', [ 'comments_cooldown' ] );
	}

	public function test_login_and_ips_diagnosis_surface_existing_hidden_callouts() :void {
		$loginPayload = $this->renderConfigureDiagnosis( [
			'zone' => 'login',
		] );
		$ipsPayload = $this->renderConfigureDiagnosis( [
			'zone' => 'ips',
		] );

		$this->assertDiagnosisRowScope( $loginPayload, 'two_factor_general', 'two_factor_auth', [ 'mfa_verify_page', 'allow_backupcodes' ] );
		$this->assertDiagnosisRowScope( $loginPayload, 'two_factor_email', 'two_factor_auth', [ 'enable_email_authentication' ] );
		$this->assertDiagnosisRowScope( $loginPayload, 'two_factor_otp_passkeys', 'two_factor_auth', [ 'enable_google_authenticator', 'enable_passkeys' ] );
		$this->assertDiagnosisRowScope( $loginPayload, 'hide_wp_login', 'hide_wp_login' );
		$this->assertDiagnosisRowScope( $loginPayload, 'session_theft_protection', 'session_theft_protection', [ 'enable_user_login_email_notification', 'session_lock' ] );
		$this->assertDiagnosisRowScope( $ipsPayload, 'crowdsec_blocking', 'crowdsec_blocking', [ 'cs_block', 'cs_enroll_id' ] );
		$this->assertDiagnosisRowScope( $ipsPayload, 'auto_ip_blocking', 'auto_ip_blocking', [ 'user_auto_recover', 'request_whitelist' ] );
		$this->assertDiagnosisRowScope( $ipsPayload, 'bot_actions', 'bot_actions', [ 'track_xmlrpc' ] );
		$this->assertNull( $this->findDiagnosisRowByKey( $ipsPayload, 'ip_blocking_rules' ) );
	}

	public function test_users_and_firewall_diagnosis_surface_corrected_option_ownership() :void {
		$usersPayload = $this->renderConfigureDiagnosis( [
			'zone' => 'users',
		] );
		$firewallPayload = $this->renderConfigureDiagnosis( [
			'zone' => 'firewall',
		] );

		$this->assertDiagnosisRowScope( $usersPayload, 'inactive_users', 'inactive_users', [ 'manual_suspend', 'auto_password' ] );
		$usersGeneral = $this->findDiagnosisRowByKey( $usersPayload, 'general_settings' );
		if ( $usersGeneral !== null ) {
			$this->assertStringNotContainsString( 'manual_suspend', (string)( $usersGeneral[ 'expand_action' ][ 'data_attributes' ][ 'option_keys' ] ?? '' ) );
			$this->assertStringNotContainsString( 'auto_password', (string)( $usersGeneral[ 'expand_action' ][ 'data_attributes' ][ 'option_keys' ] ?? '' ) );
			$this->assertStringNotContainsString( 'enable_user_login_email_notification', (string)( $usersGeneral[ 'expand_action' ][ 'data_attributes' ][ 'option_keys' ] ?? '' ) );
		}

		$this->assertDiagnosisRowScope( $firewallPayload, 'web_application_firewall', 'web_application_firewall', [ 'block_send_email' ] );
		$this->assertDiagnosisRowScope( $firewallPayload, 'general_settings', 'module_firewall', [ 'clean_wp_rubbish' ] );
		$xmlRpcRow = $this->findDiagnosisRowByKey( $firewallPayload, 'xml_rpc_disable' );
		if ( $xmlRpcRow !== null ) {
			$this->assertStringNotContainsString( 'track_xmlrpc', (string)( $xmlRpcRow[ 'expand_action' ][ 'data_attributes' ][ 'option_keys' ] ?? '' ) );
		}
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
			$this->assertSame( 'href', (string)( $enabledPayload[ 'header' ][ 'actions' ][ 0 ][ 'kind' ] ?? '' ) );
			$this->assertSame( 'deactivate', (string)( $enabledPayload[ 'header' ][ 'actions' ][ 0 ][ 'type' ] ?? '' ) );
			$this->assertNotSame( '', (string)( $enabledPayload[ 'header' ][ 'actions' ][ 0 ][ 'href' ] ?? '' ) );
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
		$rows = $usersTiles[ 0 ][ 'panel' ][ 'rows' ] ?? [];

		$this->assertNotContains( 'Default Admin User', \array_column( $rows, 'title' ) );
		foreach ( $rows as $row ) {
			$this->assertNotSame( [], $row[ 'config_action' ] ?? [] );
		}
	}

	public function test_real_tile_build_only_keeps_intentional_visible_duplicate_option_ownership() :void {
		$tiles = ( new ConfigureZoneTilesBuilder() )->build();
		$allowedDuplicates = [
			'enable_password_policies' => [
				'users:password_policies',
				'users:pwned_passwords',
				'users:password_strength',
			],
		];
		$unexpectedDuplicates = [];
		$visibleOptionRows = [];

		foreach ( $tiles as $tile ) {
			foreach ( $tile[ 'panel' ][ 'rows' ] ?? [] as $row ) {
				$optionKeys = \array_filter( \array_map(
					'trim',
					\explode( ',', (string)( $row[ 'config_action' ][ 'data' ][ 'option_keys' ] ?? '' ) )
				) );
				foreach ( $optionKeys as $optionKey ) {
					$visibleOptionRows[ $optionKey ][] = $tile[ 'key' ].':'.(string)( $row[ 'key' ] ?? '' );
				}
			}
		}

		foreach ( $visibleOptionRows as $optionKey => $rowKeys ) {
			if ( \count( $rowKeys ) < 2 ) {
				continue;
			}

			sort( $rowKeys );
			$allowedRows = $allowedDuplicates[ $optionKey ] ?? [];
			sort( $allowedRows );

			if ( $rowKeys === $allowedRows ) {
				continue;
			}

			$unexpectedDuplicates[ $optionKey ] = $rowKeys;
		}

		$this->assertSame( [], $unexpectedDuplicates );
	}

	public function test_real_builder_reflects_grouped_and_downgraded_warning_rows() :void {
		$snapshot = $this->snapshotSelectedOptions( [
			'enable_core_file_integrity_scan',
			'file_scan_areas',
			'file_repair_areas',
			'disable_xmlrpc',
		] );

		try {
			$this->requireController()->opts
				->optSet( 'enable_core_file_integrity_scan', 'Y' )
				->optSet( 'file_scan_areas', [ 'malware_php', 'plugins', 'themes', 'wpcontent', 'wproot' ] )
				->optSet( 'file_repair_areas', [ 'plugin', 'theme' ] )
				->optSet( 'disable_xmlrpc', 'N' )
				->store();

			$tiles = ( new ConfigureZoneTilesBuilder() )->build();
			$fileScanningRow = $this->findBuiltConfigureRowByKey( $tiles, 'file_scanning' );
			$xmlRpcRow = $this->findBuiltConfigureRowByKey( $tiles, 'xml_rpc_disable' );

			$this->assertNotNull( $fileScanningRow );
			$this->assertNotNull( $xmlRpcRow );
			$this->assertSame( 'okay', $fileScanningRow[ 'enabled_status' ] ?? null );
			$this->assertSame( 'okay', $xmlRpcRow[ 'enabled_status' ] ?? null );
		}
		finally {
			$this->restoreSelectedOptions( $snapshot );
		}
	}

	private function findConfigureOptionResultByConfigItem( array $results, string $configItem ) :?array {
		foreach ( $results as $result ) {
			if ( !\is_array( $result ) || ( $result[ 'type' ] ?? '' ) !== 'option' ) {
				continue;
			}

			$focusRequest = \json_decode( (string)( $result[ 'focus_request_json' ] ?? '' ), true );
			if ( \is_array( $focusRequest ) && ( $focusRequest[ 'config_item' ] ?? '' ) === $configItem ) {
				return $result;
			}
		}

		return null;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private function findDiagnosisRowByKey( array $payload, string $rowKey ) :?array {
		foreach ( [ 'problem_rows', 'review_rows', 'healthy_rows' ] as $groupKey ) {
			foreach ( (array)( $payload[ $groupKey ] ?? [] ) as $row ) {
				if ( \is_array( $row ) && ( $row[ 'key' ] ?? '' ) === $rowKey ) {
					return $row;
				}
			}
		}

		return null;
	}

	private function assertDiagnosisRowScope(
		array $payload,
		string $rowKey,
		string $zoneComponentSlug,
		array $requiredOptionKeys = [],
		?string $configItem = null
	) :void {
		$row = $this->findDiagnosisRowByKey( $payload, $rowKey );
		$this->assertNotNull( $row, 'Missing diagnosis row: '.$rowKey );
		$this->assertTrue( (bool)( $row[ 'expand_action' ][ 'is_expandable' ] ?? false ) );
		$this->assertSame(
			$zoneComponentSlug,
			(string)( $row[ 'expand_action' ][ 'data_attributes' ][ 'zone_component_slug' ] ?? '' )
		);

		$optionKeys = \array_filter( \array_map(
			'trim',
			\explode( ',', (string)( $row[ 'expand_action' ][ 'data_attributes' ][ 'option_keys' ] ?? '' ) )
		) );
		foreach ( $requiredOptionKeys as $requiredOptionKey ) {
			$this->assertContains( $requiredOptionKey, $optionKeys );
		}

		if ( $configItem !== null ) {
			$this->assertSame(
				$configItem,
				(string)( $row[ 'expand_action' ][ 'data_attributes' ][ 'config_item' ] ?? '' )
			);
		}
	}

	private function assertHrefQueryMatches( string $href, array $expectedQuery ) :void {
		$this->assertNotSame( '', $href );

		$query = (string)( \parse_url( $href, \PHP_URL_QUERY ) ?? '' );
		parse_str( $query, $queryArgs );

		foreach ( $expectedQuery as $key => $value ) {
			$this->assertSame( $value, (string)( $queryArgs[ $key ] ?? '' ) );
		}
	}

	private function findBuiltConfigureRowByKey( array $tiles, string $rowKey ) :?array {
		foreach ( $tiles as $tile ) {
			foreach ( $tile[ 'panel' ][ 'rows' ] ?? [] as $row ) {
				if ( ( $row[ 'key' ] ?? '' ) === $rowKey ) {
					return $row;
				}
			}
		}

		return null;
	}
}
