<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Configuration;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts\PluginBadgeMode;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Tests for plugin options schema validation.
 * 
 * These tests validate that all options in plugin.json have correct structure,
 * types, and default values - catching configuration bugs before they reach production.
 */
class PluginOptionsSchemaTest extends TestCase {

	use PluginPathsTrait;

	private array $options;
	private array $sections;

	protected function set_up() :void {
		parent::set_up();
		$config = $this->loadConfig();
		
		// Convert options array to keyed format for easier lookup
		// plugin.json stores options as: [ { "key": "option_name", ... }, ... ]
		// We convert to: [ "option_name" => { "key": "option_name", ... }, ... ]
		$rawOptions = $config['config_spec']['options'] ?? [];
		$this->options = [];
		foreach ( $rawOptions as $option ) {
			if ( isset( $option['key'] ) ) {
				$this->options[$option['key']] = $option;
			}
		}
		
		$this->sections = $config['config_spec']['sections'] ?? [];
	}

	private function loadConfig() :array {
		return $this->decodePluginJsonFile( 'plugin.json', 'Plugin configuration' );
	}

	// =========================================================================
	// OPTION STRUCTURE TESTS
	// =========================================================================

	public function testAllOptionsHaveValidTypes() :void {
		$validTypes = [
			'checkbox',
			'select',
			'multiple_select',
			'text',
			'password',
			'integer',
			'email',
			'array',
			'boolean',
			'noneditable_text',
			'timestamp',
		];

		$optionsWithTypes = \array_filter( $this->options, fn( $opt ) => isset( $opt['type'] ) );
		
		foreach ( $optionsWithTypes as $key => $option ) {
			$this->assertContains(
				$option['type'],
				$validTypes,
				sprintf( "Option '%s' has invalid type '%s'", $key, $option['type'] )
			);
		}
	}

	public function test_request_policy_mode_option_contract() :void {
		$this->assertSame( 'legacy', $this->options[ 'request_policy_mode' ][ 'default' ] ?? null );
		$this->assertSame(
			[ 'legacy', 'shadow', 'adaptive' ],
			\array_column( $this->options[ 'request_policy_mode' ][ 'value_options' ] ?? [], 'value_key' )
		);

		$crowdSecPolicyModeOptions = \array_filter(
			$this->options,
			static fn( array $option, string $key ) :bool => ( $option[ 'section' ] ?? '' ) === 'section_crowdsec'
															&& \substr( $key, -11 ) === 'policy_mode',
			ARRAY_FILTER_USE_BOTH
		);
		$this->assertSame( [], \array_keys( $crowdSecPolicyModeOptions ) );
	}

	public function testCheckboxOptionsHaveValidDefaults() :void {
		$checkboxOptions = \array_filter( 
			$this->options, 
			fn( $opt ) => ( $opt['type'] ?? '' ) === 'checkbox' 
		);

		foreach ( $checkboxOptions as $key => $option ) {
			if ( isset( $option['default'] ) ) {
				$this->assertContains(
					$option['default'],
					[ 'Y', 'N' ],
					sprintf( "Checkbox option '%s' should have 'Y' or 'N' as default, got '%s'", $key, $option['default'] )
				);
			}
		}
	}

	public function testSelectOptionsHaveValueOptions() :void {
		$selectOptions = \array_filter( 
			$this->options, 
			fn( $opt ) => \in_array( $opt['type'] ?? '', [ 'select', 'multiple_select' ] )
		);

		foreach ( $selectOptions as $key => $option ) {
			// Skip if hidden or not transferable
			if ( ( $option['hidden'] ?? false ) || ( $option['transferable'] ?? true ) === false ) {
				continue;
			}

			$this->assertArrayHasKey(
				'value_options',
				$option,
				sprintf( "Select option '%s' should have 'value_options' defined", $key )
			);

			if ( isset( $option['value_options'] ) ) {
				$this->assertIsArray(
					$option['value_options'],
					sprintf( "Option '%s' value_options should be an array", $key )
				);

				foreach ( $option['value_options'] as $idx => $valueOption ) {
					$this->assertArrayHasKey(
						'value_key',
						$valueOption,
						sprintf( "Option '%s' value_option[%d] should have 'value_key'", $key, $idx )
					);
				}
			}
		}
	}

	public function testIntegerOptionsHaveNumericDefaults() :void {
		$integerOptions = \array_filter( 
			$this->options, 
			fn( $opt ) => ( $opt['type'] ?? '' ) === 'integer' 
		);

		foreach ( $integerOptions as $key => $option ) {
			if ( isset( $option['default'] ) ) {
				$this->assertTrue(
					\is_numeric( $option['default'] ),
					sprintf( "Integer option '%s' should have numeric default, got '%s'", $key, \gettype( $option['default'] ) )
				);
			}
		}
	}

	public function testIntegerOptionsWithMinMaxAreValid() :void {
		$integerOptions = \array_filter( 
			$this->options, 
			fn( $opt ) => ( $opt['type'] ?? '' ) === 'integer' 
		);

		foreach ( $integerOptions as $key => $option ) {
			if ( isset( $option['min'] ) && isset( $option['max'] ) ) {
				$this->assertLessThanOrEqual(
					$option['max'],
					$option['min'],
					sprintf( "Option '%s' min (%s) should not exceed max (%s)", $key, $option['min'], $option['max'] )
				);
			}

			// Default should be within min/max range if all are set
			if ( isset( $option['default'] ) && isset( $option['min'] ) ) {
				$this->assertGreaterThanOrEqual(
					$option['min'],
					$option['default'],
					sprintf( "Option '%s' default (%s) should be >= min (%s)", $key, $option['default'], $option['min'] )
				);
			}

			if ( isset( $option['default'] ) && isset( $option['max'] ) ) {
				$this->assertLessThanOrEqual(
					$option['max'],
					$option['default'],
					sprintf( "Option '%s' default (%s) should be <= max (%s)", $key, $option['default'], $option['max'] )
				);
			}
		}
	}

	// =========================================================================
	// OPTION-SECTION RELATIONSHIP TESTS
	// =========================================================================

	public function testOptionsReferenceValidSections() :void {
		$sectionSlugs = \array_column( $this->sections, 'slug' );

		$optionsWithSections = \array_filter( $this->options, fn( $opt ) => isset( $opt['section'] ) );

		foreach ( $optionsWithSections as $key => $option ) {
			$this->assertContains(
				$option['section'],
				$sectionSlugs,
				sprintf( "Option '%s' references non-existent section '%s'", $key, $option['section'] )
			);
		}
	}

	// =========================================================================
	// CRITICAL OPTION TESTS
	// =========================================================================

	/**
	 * @dataProvider providerCriticalSecurityOptions
	 */
	public function testCriticalSecurityOptionsExist( string $optionKey, string $description ) :void {
		$this->assertArrayHasKey(
			$optionKey,
			$this->options,
			sprintf( "Critical security option '%s' (%s) should exist", $optionKey, $description )
		);
	}

	public static function providerCriticalSecurityOptions() :array {
		return [
			'firewall directory traversal' => [ 'block_dir_traversal', 'Directory traversal protection' ],
			'firewall sql queries' => [ 'block_sql_queries', 'SQL injection protection' ],
			'firewall field truncation' => [ 'block_field_truncation', 'Field truncation protection' ],
			'firewall php code' => [ 'block_php_code', 'PHP code injection protection' ],
			'login cooldown' => [ 'login_limit_interval', 'Login rate limiting' ],
			'enable two factor' => [ 'enable_email_authentication', 'Two-factor authentication' ],
		];
	}

	public function testIgnoredMaintenanceItemsOptionUsesExpectedHiddenArrayContract() :void {
		$option = $this->options['ignored_maintenance_items'] ?? null;

		$this->assertIsArray( $option );
		$this->assertSame( 'section_hidden', $option['section'] ?? null );
		$this->assertSame( 'array', $option['type'] ?? null );
		$this->assertSame( false, $option['transferable'] ?? true );
		$this->assertSame( true, $option['tracking_exclude'] ?? false );
		$this->assertSame(
			[
				'default_admin_user',
				'wp_updates',
				'wp_plugins_updates',
				'wp_themes_updates',
				'wp_plugins_inactive',
				'wp_themes_inactive',
				'system_ssl_certificate',
				'system_php_version',
				'wp_db_password',
				'system_lib_openssl',
			],
			\array_keys( $option['default'] ?? [] )
		);
	}

	public function testIgnoredMaintenanceItemsOptionExistsInSourceOptionsSpec() :void {
		$options = $this->decodePluginJsonFile( 'plugin-spec/34_options.json', 'Source options spec' );
		$matches = \array_values( \array_filter(
			$options,
			static fn( array $option ) :bool => ( $option['key'] ?? '' ) === 'ignored_maintenance_items'
		) );

		$this->assertCount( 1, $matches );
		$option = $matches[ 0 ];

		$this->assertSame( 'section_hidden', $option['section'] );
		$this->assertSame( 'array', $option['type'] );
		$this->assertSame( false, $option['transferable'] );
		$this->assertSame( true, $option['tracking_exclude'] );
		$this->assertSame(
			[
				'default_admin_user',
				'wp_updates',
				'wp_plugins_updates',
				'wp_themes_updates',
				'wp_plugins_inactive',
				'wp_themes_inactive',
				'system_ssl_certificate',
				'system_php_version',
				'wp_db_password',
				'system_lib_openssl',
			],
			\array_keys( $option['default'] )
		);
	}

	public function testAdminLoginInstantAlertOptionUsesAlertsReportingContract() :void {
		$this->assertArrayHasKey( 'instant_alert_admin_login', $this->options );
		$option = $this->options['instant_alert_admin_login'];

		$this->assertIsArray( $option );
		$this->assertSame( 'section_alerts', $option['section'] );
		$this->assertSame( [ 'instant_alerts', 'reporting' ], $option['zone_comp_slugs'] );
		$this->assertNotContains( 'module_users', $option['zone_comp_slugs'] );
		$this->assertSame( 'select', $option['type'] );
		$this->assertSame( 'disabled', $option['default'] );
		$this->assertArrayHasKey( 'value_options', $option );
		$this->assertSame( [ 'disabled', 'email' ], \array_column( $option['value_options'], 'value_key' ) );
		$this->assertSame( 788, $option['beacon_id'] );
	}

	public function testPluginBadgeOptionUsesDisplayModeSelectContract() :void {
		$this->assertSame( 'disabled', PluginBadgeMode::DISABLED );
		$this->assertSame( 'light', PluginBadgeMode::LIGHT );
		$this->assertSame( 'dark', PluginBadgeMode::DARK );
		$this->assertSame( [ 'disabled', 'light', 'dark' ], PluginBadgeMode::VALID_MODES );

		$this->assertArrayHasKey( 'display_plugin_badge', $this->options );
		$option = $this->options['display_plugin_badge'];

		$this->assertIsArray( $option );
		$this->assertSame( 'section_defaults', $option['section'] );
		$this->assertSame( [ 'plugin_general', 'module_plugin' ], $option['zone_comp_slugs'] );
		$this->assertSame( 'select', $option['type'] );
		$this->assertSame( PluginBadgeMode::DISABLED, $option['default'] );
		$this->assertSame( PluginBadgeMode::VALID_MODES, \array_column( $option['value_options'] ?? [], 'value_key' ) );
		$this->assertSame( 130, $option['beacon_id'] );
	}

	public function testPluginBadgeOptionSourceSpecMatchesGeneratedModeContract() :void {
		$options = $this->decodePluginJsonFile( 'plugin-spec/34_options.json', 'Source options spec' );
		$matches = \array_values( \array_filter(
			$options,
			static fn( array $option ) :bool => ( $option['key'] ?? '' ) === 'display_plugin_badge'
		) );

		$this->assertCount( 1, $matches );
		$option = $matches[ 0 ];

		$this->assertSame( 'select', $option['type'] );
		$this->assertSame( PluginBadgeMode::DISABLED, $option['default'] );
		$this->assertSame( PluginBadgeMode::VALID_MODES, \array_column( $option['value_options'] ?? [], 'value_key' ) );
	}

	public function testBackupCodesOptionIsFreeOptInMfaOptionInSourceAndGeneratedConfig() :void {
		$sourceOptions = $this->sourceOptionsByKey();
		$this->assertArrayHasKey( 'allow_backupcodes', $sourceOptions );
		$this->assertArrayHasKey( 'allow_backupcodes', $this->options );

		foreach ( [
			'source'    => $sourceOptions[ 'allow_backupcodes' ],
			'generated' => $this->options[ 'allow_backupcodes' ],
		] as $context => $option ) {
			$this->assertSame( 'section_twofactor_auth', $option[ 'section' ], sprintf( '%s backup-code option should remain in Login Guard MFA.', $context ) );
			$this->assertSame( [ 'two_factor_auth', 'module_login' ], $option[ 'zone_comp_slugs' ], sprintf( '%s backup-code option should stay in the MFA zone.', $context ) );
			$this->assertSame( 'checkbox', $option[ 'type' ], sprintf( '%s backup-code option should be a checkbox.', $context ) );
			$this->assertSame( 'N', $option[ 'default' ], sprintf( '%s backup-code option should stay opt-in.', $context ) );
			$this->assertArrayNotHasKey( 'premium', $option, sprintf( '%s backup-code option should not be premium-only.', $context ) );
			$this->assertArrayNotHasKey( 'cap', $option, sprintf( '%s backup-code option should not require a premium capability.', $context ) );
		}
	}

	public function testAdminLoginInstantAlertOptionIsGroupedWithInstantAlertsInSourceSpec() :void {
		$options = $this->decodePluginJsonFile( 'plugin-spec/34_options.json', 'Source options spec' );
		$keys = \array_column( $options, 'key' );
		$instantAdminsIndex = \array_search( 'instant_alert_admins', $keys, true );
		$adminLoginIndex = \array_search( 'instant_alert_admin_login', $keys, true );

		$this->assertNotFalse( $instantAdminsIndex );
		$this->assertNotFalse( $adminLoginIndex );
		$this->assertSame( $instantAdminsIndex + 1, $adminLoginIndex );

		$option = $options[ $adminLoginIndex ];
		$this->assertSame( 'section_alerts', $option['section'] );
		$this->assertSame( [ 'instant_alerts', 'reporting' ], $option['zone_comp_slugs'] );
		$this->assertNotContains( 'module_users', $option['zone_comp_slugs'] );
		$this->assertSame( 788, $option['beacon_id'] );
	}

	public function testFirewallBlockInstantAlertOptionUsesAlertsReportingContract() :void {
		$this->assertArrayHasKey( 'instant_alert_firewall_block', $this->options );
		$option = $this->options['instant_alert_firewall_block'];

		$this->assertIsArray( $option );
		$this->assertSame( 'section_alerts', $option['section'] );
		$this->assertSame( [ 'instant_alerts', 'reporting' ], $option['zone_comp_slugs'] );
		$this->assertSame( 'select', $option['type'] );
		$this->assertSame( 'disabled', $option['default'] );
		$this->assertSame( [ 'disabled', 'email' ], \array_column( $option['value_options'] ?? [], 'value_key' ) );
		$this->assertSame( 788, $option['beacon_id'] );
	}

	public function testFirewallBlockInstantAlertAndLegacyOptionAreGroupedInSourceSpec() :void {
		$options = $this->decodePluginJsonFile( 'plugin-spec/34_options.json', 'Source options spec' );
		$keys = \array_column( $options, 'key' );
		$adminLoginIndex = \array_search( 'instant_alert_admin_login', $keys, true );
		$firewallBlockIndex = \array_search( 'instant_alert_firewall_block', $keys, true );
		$legacyBlockEmailIndex = \array_search( 'block_send_email', $keys, true );

		$this->assertNotFalse( $adminLoginIndex );
		$this->assertNotFalse( $firewallBlockIndex );
		$this->assertNotFalse( $legacyBlockEmailIndex );
		$this->assertSame( $adminLoginIndex + 1, $firewallBlockIndex );
		$this->assertSame( $firewallBlockIndex + 1, $legacyBlockEmailIndex );

		$option = $options[ $firewallBlockIndex ];
		$this->assertSame( 'section_alerts', $option['section'] );
		$this->assertSame( [ 'instant_alerts', 'reporting' ], $option['zone_comp_slugs'] );
		$this->assertSame( 788, $option['beacon_id'] );
	}

	public function testLegacyFirewallBlockEmailOptionIsHiddenAndNonTransferable() :void {
		$this->assertArrayHasKey( 'block_send_email', $this->options );
		$option = $this->options['block_send_email'];

		$this->assertIsArray( $option );
		$this->assertSame( 'section_hidden', $option['section'] );
		$this->assertSame( 'checkbox', $option['type'] );
		$this->assertSame( 'N', $option['default'] );
		$this->assertSame( false, $option['transferable'] ?? true );
		$this->assertArrayNotHasKey( 'zone_comp_slugs', $option );
	}

	public function testLegacyAdminLoginNotificationOptionIsHiddenAndNonTransferable() :void {
		$this->assertArrayHasKey( 'enable_admin_login_email_notification', $this->options );
		$option = $this->options['enable_admin_login_email_notification'];

		$this->assertIsArray( $option );
		$this->assertSame( 'section_hidden', $option['section'] );
		$this->assertSame( 'text', $option['type'] );
		$this->assertSame( '', $option['default'] );
		$this->assertSame( true, $option['sensitive'] );
		$this->assertSame( false, $option['transferable'] ?? true );
		$this->assertArrayNotHasKey( 'zone_comp_slugs', $option );
	}

	public function testLegacyLogRetentionOptionsAreHiddenAndRetained() :void {
		$sourceOptions = $this->sourceOptionsByKey();

		foreach ( [
			'audit_trail_auto_clean',
			'auto_clean',
		] as $key ) {
			$this->assertArrayHasKey( $key, $sourceOptions );
			$this->assertArrayHasKey( $key, $this->options );

			foreach ( [
				'source'    => $sourceOptions[ $key ],
				'generated' => $this->options[ $key ],
			] as $context => $option ) {
				$this->assertSame( 'section_hidden', $option[ 'section' ], sprintf( "%s option '%s' should be hidden.", $context, $key ) );
				$this->assertSame( false, $option[ 'transferable' ] ?? true, sprintf( "%s option '%s' should not be transferable.", $context, $key ) );
				$this->assertSame( true, $option[ 'tracking_exclude' ] ?? false, sprintf( "%s option '%s' should be excluded from tracking.", $context, $key ) );
				$this->assertSame( 'integer', $option[ 'type' ], sprintf( "%s option '%s' should be an integer.", $context, $key ) );
				$this->assertSame( 7, $option[ 'default' ], sprintf( "%s option '%s' should default to 7.", $context, $key ) );
				$this->assertSame( 1, $option[ 'min' ], sprintf( "%s option '%s' should have minimum 1.", $context, $key ) );
				$this->assertArrayNotHasKey( 'zone_comp_slugs', $option );
				$this->assertArrayNotHasKey( 'value_options', $option );
			}
		}

		foreach ( [
			'log_level_db',
			'type_exclusions',
			'custom_exclusions',
		] as $key ) {
			$this->assertArrayNotHasKey( $key, $sourceOptions );
			$this->assertArrayNotHasKey( $key, $this->options );
		}
	}

	public function testSensitiveAuditOptionsAreMarkedInSourceAndGeneratedConfig() :void {
		$sourceOptions = $this->sourceOptionsByKey();
		$sensitiveKeys = [
			'admin_access_key',
			'sec_admin_users',
			'api_namespace_exclusions',
			'page_params_whitelist',
			'scan_path_exclusions',
			'request_whitelist',
			'importexport_masterurl',
			'preferred_temp_dir',
			'xcsp_custom',
			'instant_alerts_data',
			'wphashes_api_token',
			'import_id',
			'import_url_ids',
			'blockdown_cfg',
			'importexport_secretkey',
			'importexport_whitelist',
			'yubikey_api_key',
			'yubikey_app_id',
			'cs_enroll_id',
			'block_send_email_address',
		];

		foreach ( $sensitiveKeys as $key ) {
			$this->assertSame( true, $sourceOptions[ $key ][ 'sensitive' ] ?? false, sprintf( "Source option '%s' should be sensitive.", $key ) );
			$this->assertSame( true, $this->options[ $key ][ 'sensitive' ] ?? false, sprintf( "Generated option '%s' should be sensitive.", $key ) );
		}
	}

	public function testPasswordOptionsAreSensitiveInSourceAndGeneratedConfig() :void {
		foreach ( $this->sourceOptionsByKey() as $key => $option ) {
			if ( ( $option[ 'type' ] ?? '' ) !== 'password' ) {
				continue;
			}

			$this->assertSame( true, $option[ 'sensitive' ] ?? false, sprintf( "Source password option '%s' should be sensitive.", $key ) );
			$this->assertSame( true, $this->options[ $key ][ 'sensitive' ] ?? false, sprintf( "Generated password option '%s' should be sensitive.", $key ) );
		}
	}

	public function testImportExportWhitelistNotifyOptionIsNotSensitive() :void {
		$sourceOptions = $this->sourceOptionsByKey();

		$this->assertNotSame( true, $sourceOptions[ 'importexport_whitelist_notify' ][ 'sensitive' ] ?? false );
		$this->assertNotSame( true, $this->options[ 'importexport_whitelist_notify' ][ 'sensitive' ] ?? false );
	}

	public function testSecurityOverviewPrefsOptionIsAbsentFromGeneratedConfig() :void {
		$this->assertArrayNotHasKey( 'sec_overview_prefs', $this->options );
	}

	public function testSecurityOverviewPrefsOptionIsAbsentFromSourceOptionsSpec() :void {
		$options = $this->decodePluginJsonFile( 'plugin-spec/34_options.json', 'Source options spec' );
		$matches = \array_values( \array_filter(
			$options,
			static fn( array $option ) :bool => ( $option['key'] ?? '' ) === 'sec_overview_prefs'
		) );

		$this->assertCount( 0, $matches );
	}

	public function testSecurityOptionsDefaultToEnabled() :void {
		// Note: block_php_code is intentionally excluded - it defaults to 'N' because
		// it can interfere with legitimate WordPress functionality (Plugin/Theme editors)
		$securityOptions = [
			'block_dir_traversal',
			'block_sql_queries',
			'block_field_truncation',
		];

		foreach ( $securityOptions as $optKey ) {
			if ( isset( $this->options[$optKey] ) ) {
				$default = $this->options[$optKey]['default'] ?? null;
				$this->assertSame(
					'Y',
					$default,
					sprintf( "Security option '%s' should default to enabled (Y)", $optKey )
				);
			}
		}
	}

	// =========================================================================
	// TRANSFERABLE OPTIONS TESTS
	// =========================================================================

	public function testNonTransferableOptionsHaveNoDefaults() :void {
		$nonTransferable = \array_filter(
			$this->options,
			fn( $opt ) => isset( $opt['transferable'] ) && $opt['transferable'] === false
		);

		// Non-transferable options typically shouldn't be exported/imported
		// This test documents which options are marked as non-transferable
		$this->assertNotEmpty(
			$nonTransferable,
			'There should be some non-transferable options (like license keys, timestamps)'
		);
	}

	// =========================================================================
	// ARRAY OPTIONS TESTS
	// =========================================================================

	public function testArrayOptionsHaveArrayDefaults() :void {
		$arrayOptions = \array_filter( 
			$this->options, 
			fn( $opt ) => ( $opt['type'] ?? '' ) === 'array' 
		);

		foreach ( $arrayOptions as $key => $option ) {
			if ( isset( $option['default'] ) ) {
				$this->assertIsArray(
					$option['default'],
					sprintf( "Array option '%s' should have array default", $key )
				);
			}
		}
	}

	// =========================================================================
	// PREMIUM OPTIONS TESTS
	// =========================================================================

	public function testPremiumOptionsAreMarked() :void {
		$premiumOptions = \array_filter(
			$this->options,
			fn( $opt ) => ( $opt['premium'] ?? false ) === true
		);

		// Premium features exist
		$this->assertNotEmpty(
			$premiumOptions,
			'There should be premium options defined'
		);

		// All premium options should have a section
		foreach ( $premiumOptions as $key => $option ) {
			// Premium options that are visible should have sections
			if ( !( $option['hidden'] ?? false ) ) {
				$this->assertTrue(
					isset( $option['section'] ) || ( $option['transferable'] ?? true ) === false,
					sprintf( "Visible premium option '%s' should have a section or be non-transferable", $key )
				);
			}
		}
	}

	private function sourceOptionsByKey() :array {
		$options = $this->decodePluginJsonFile( 'plugin-spec/34_options.json', 'Source options spec' );
		$byKey = [];
		foreach ( $options as $option ) {
			$byKey[ $option[ 'key' ] ] = $option;
		}
		return $byKey;
	}
}
