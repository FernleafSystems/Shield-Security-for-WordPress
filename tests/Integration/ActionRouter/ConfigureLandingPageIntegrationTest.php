<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\AjaxRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ConfigureDrillDownDiagnosis;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ConfigureLandingViewBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ConfigureSearchResults;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ConfigureZoneTilesBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageConfigureLanding;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\PluginAdminRouteRenderAssertions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ConfigureLandingPageIntegrationTest extends ShieldIntegrationTestCase {

	use PluginAdminRouteRenderAssertions;

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	private function renderConfigureLandingPage( array $params = [] ) :array {
		$payload = $this->processActionPayloadWithAdminBypass( PageConfigureLanding::SLUG, \array_merge( [
			Constants::NAV_ID     => PluginNavs::NAV_ZONES,
			Constants::NAV_SUB_ID => PluginNavs::SUBNAV_ZONES_OVERVIEW,
		], $params ) );
		$this->assertRouteRenderOutputHealthy( $payload, 'configure landing' );
		return $payload;
	}

	private function renderConfigureDiagnosis( array $params = [] ) :array {
		return $this->processActionPayloadWithAdminBypass( ConfigureDrillDownDiagnosis::SLUG, $params );
	}

	private function renderConfigureSearchResults( array $params = [] ) :array {
		$payload = $this->processActionPayloadWithAdminBypass( ConfigureSearchResults::SLUG, $params );
		$this->assertRouteRenderOutputHealthy( $payload, 'configure search results' );
		return $payload;
	}

	public function test_landing_renders_shared_operator_chrome_and_two_layer_drill_shell() :void {
		$payload = $this->renderConfigureLandingPage();
		$this->assertIsArray( $payload[ 'render_data' ][ 'vars' ] ?? null );
		$vars = $payload[ 'render_data' ][ 'vars' ];
		$diagnosisAction = \json_decode( (string)( $vars[ 'configure_ajax' ][ 'diagnosis_render_action_json' ] ?? '' ), true );

		$modeShell = $vars[ 'mode_shell' ] ?? [];
		$this->assertIsArray( $modeShell );
		$this->assertFalse( (bool)( $modeShell[ 'is_interactive' ] ?? true ) );
		$this->assertTrue( (bool)( $modeShell[ 'is_mode_landing' ] ?? false ) );
		$this->assertTrue( (bool)( $modeShell[ 'use_operator_chrome' ] ?? false ) );
		$this->assertArrayNotHasKey( 'accent_status', $modeShell );
		$this->assertIsArray( $modeShell[ 'root_step' ] ?? null );
		$this->assertIsArray( \json_decode( (string)( $modeShell[ 'root_step_json' ] ?? '' ), true ) );
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
	}

	public function test_valid_deep_link_starts_on_diagnosis_and_invalid_key_falls_back() :void {
		$validPayload = $this->renderConfigureLandingPage( [ 'zone' => 'login' ] );
		$invalidPayload = $this->renderConfigureLandingPage( [ 'zone' => 'login_protection' ] );

		$this->assertSame( 1, (int)( $validPayload[ 'render_data' ][ 'vars' ][ 'drill_shell' ][ 'active_index' ] ?? -1 ) );
		$this->assertSame( 0, (int)( $invalidPayload[ 'render_data' ][ 'vars' ][ 'drill_shell' ][ 'active_index' ] ?? -1 ) );
	}

	public function test_landing_search_dock_exposes_search_action_and_normalized_focus_payload() :void {
		$payload = $this->renderConfigureLandingPage( [
			'zone'        => 'spam',
			'row_key'     => 'general_settings',
			'config_item' => 'comments_cooldown',
		] );

		$vars = $payload[ 'render_data' ][ 'vars' ] ?? [];
		$searchAction = \json_decode( (string)( $vars[ 'configure_ajax' ][ 'search_render_action_json' ] ?? '' ), true );

		$this->assertSame(
			ConfigureSearchResults::SLUG,
			$searchAction[ 'render_slug' ] ?? ''
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

		$this->assertSame( 'login', (string)( $payload[ 'zone_selection' ][ 'key' ] ?? '' ) );
		$this->assertIsArray( $payload[ 'header' ] ?? null );
		$this->assertArrayNotHasKey( 'diagnosis', $payload );
		$this->assertArrayNotHasKey( 'render_data', $payload );
		$this->assertArrayNotHasKey( 'render_output', $payload );
		$this->assertArrayNotHasKey( 'editor_selection', $payload );
		$this->assertArrayNotHasKey( 'landing_refresh', $payload );
		$this->assertIsArray( $refreshPayload[ 'landing_refresh' ] ?? null );
		$this->assertIsArray( \json_decode(
			(string)( $refreshPayload[ 'landing_refresh' ][ 'root_step_json' ] ?? '' ),
			true
		) );
	}

	public function test_search_render_returns_flat_option_and_zone_results_for_real_query() :void {
		$payload = $this->renderConfigureSearchResults( [
			'search' => 'silentcaptcha',
		] );

		$results = (array)( $payload[ 'render_data' ][ 'vars' ][ 'results' ] ?? [] );

		$this->assertNotSame( [], $results );
		$this->assertContains( 'zone', \array_column( $results, 'type' ) );
		$this->assertContains( 'option', \array_column( $results, 'type' ) );
		$optionResult = $this->findFirstConfigureOptionResultByConfigItem( $results, [
			'silentcaptcha_complexity',
			'antibot_minimum',
		] );

		$this->assertNotNull( $optionResult );
		$this->assertSame( 'option', $optionResult[ 'type' ] ?? '' );
		$this->assertSame(
			[
				'row_key'     => 'silent_captcha',
				'config_item' => (string)( \json_decode( (string)( $optionResult[ 'focus_request_json' ] ?? '' ), true )[ 'config_item' ] ?? '' ),
			],
			\json_decode( (string)( $optionResult[ 'focus_request_json' ] ?? '' ), true )
		);
		$this->assertHrefQueryMatches( (string)( $optionResult[ 'href' ] ?? '' ), [
			'zone'        => 'ips',
			'row_key'     => 'silent_captcha',
			'config_item' => (string)( \json_decode( (string)( $optionResult[ 'focus_request_json' ] ?? '' ), true )[ 'config_item' ] ?? '' ),
		] );
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

			$result = $this->findConfigureOptionResultByConfigItem(
				(array)( $payload[ 'render_data' ][ 'vars' ][ 'results' ] ?? [] ),
				$case[ 'config_item' ]
			);

			$this->assertNotNull(
				$result,
				'Configure search should return the expected option result for '.$case[ 'search' ]
			);
			$this->assertSame( 'option', $result[ 'type' ] ?? '' );
		}
	}

	public function test_search_render_returns_request_control_option_focus_contracts() :void {
		$cases = [
			[
				'search'      => 'xml-rpc',
				'row_key'     => 'xml_rpc_disable',
				'config_item' => 'disable_xmlrpc',
			],
			[
				'search'      => 'anonymous rest',
				'row_key'     => 'anon_rest_api_disable',
				'config_item' => 'disable_anonymous_restapi',
			],
			[
				'search'      => 'rest api exclusions',
				'row_key'     => 'anon_rest_api_disable',
				'config_item' => 'api_namespace_exclusions',
			],
			[
				'search'      => 'rate limiting',
				'row_key'     => 'rate_limiting',
				'config_item' => 'enable_limiter',
			],
			[
				'search'      => 'request limit',
				'row_key'     => 'rate_limiting',
				'config_item' => 'limit_requests',
			],
			[
				'search'      => 'time interval',
				'row_key'     => 'rate_limiting',
				'config_item' => 'limit_time_span',
			],
		];

		foreach ( $cases as $case ) {
			$payload = $this->renderConfigureSearchResults( [
				'search' => $case[ 'search' ],
			] );

			$result = $this->findConfigureOptionResultByConfigItem(
				(array)( $payload[ 'render_data' ][ 'vars' ][ 'results' ] ?? [] ),
				$case[ 'config_item' ]
			);

			$this->assertNotNull(
				$result,
				'Configure search should return the expected request-control option result for '.$case[ 'search' ]
			);
			$this->assertSame( 'option', $result[ 'type' ] ?? '' );
			$this->assertSame(
				[
					'row_key'     => $case[ 'row_key' ],
					'config_item' => $case[ 'config_item' ],
				],
				\json_decode( (string)( $result[ 'focus_request_json' ] ?? '' ), true )
			);
			$this->assertHrefQueryMatches( (string)( $result[ 'href' ] ?? '' ), [
				'zone'        => 'firewall',
				'row_key'     => $case[ 'row_key' ],
				'config_item' => $case[ 'config_item' ],
			] );
		}
	}

	public function test_scans_and_spam_diagnosis_render_scoped_rows_and_general_settings() :void {
		$diagnoses = ( new ConfigureLandingViewBuilder() )->build()[ 'diagnoses' ];
		$scansPayload = $diagnoses[ 'scans' ] ?? [];
		$spamPayload = $diagnoses[ 'spam' ] ?? [];

		$this->assertDiagnosisRowScope( $scansPayload, 'scan_scheduling', 'scan_scheduling', [], 'scan_frequency' );
		$this->assertDiagnosisRowScope( $spamPayload, 'trusted_commenters', 'trusted_commenters', [], 'trusted_commenter_minimum' );
		$this->assertDiagnosisRowScope( $spamPayload, 'general_settings', 'module_spam', [ 'comments_cooldown' ] );
	}

	public function test_login_and_ips_diagnosis_surface_existing_hidden_callouts() :void {
		$diagnoses = ( new ConfigureLandingViewBuilder() )->build()[ 'diagnoses' ];
		$loginPayload = $diagnoses[ 'login' ] ?? [];
		$ipsPayload = $diagnoses[ 'ips' ] ?? [];

		$this->assertDiagnosisRowScope( $loginPayload, 'two_factor_general', 'two_factor_auth', [ 'mfa_verify_page', 'allow_backupcodes' ] );
		$this->assertDiagnosisRowScope( $loginPayload, 'two_factor_email', 'two_factor_auth', [ 'enable_email_authentication' ] );
		$this->assertDiagnosisRowScope( $loginPayload, 'two_factor_otp_passkeys', 'two_factor_auth', [ 'enable_google_authenticator', 'enable_passkeys' ] );
		$this->assertDiagnosisRowScope( $loginPayload, 'login_hide', 'login_hide' );
		$this->assertDiagnosisRowScope( $loginPayload, 'session_theft_protection', 'session_theft_protection', [ 'enable_user_login_email_notification', 'session_lock' ] );
		$this->assertDiagnosisRowScope( $ipsPayload, 'crowdsec_blocking', 'crowdsec_blocking', [ 'cs_block', 'cs_enroll_id' ] );
		$this->assertDiagnosisRowScope( $ipsPayload, 'auto_ip_blocking', 'auto_ip_blocking', [ 'user_auto_recover', 'request_whitelist' ] );
		$this->assertDiagnosisRowScope( $ipsPayload, 'bot_actions', 'bot_actions', [ 'track_xmlrpc' ] );
		$this->assertNull( $this->findDiagnosisRowByKey( $ipsPayload, 'ip_blocking_rules' ) );
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

	public function test_real_builder_reflects_stable_row_status_keys() :void {
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
			$this->assertSame( 'critical', $fileScanningRow[ 'status' ] ?? null );
			$this->assertSame( 'warning', $xmlRpcRow[ 'status' ] ?? null );
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

	private function findFirstConfigureOptionResultByConfigItem( array $results, array $configItems ) :?array {
		foreach ( $configItems as $configItem ) {
			$result = $this->findConfigureOptionResultByConfigItem( $results, (string)$configItem );
			if ( $result !== null ) {
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
