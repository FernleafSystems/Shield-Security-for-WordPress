<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	ConfigureLandingViewBuilder,
	ConfigureSearchResultsBuilder
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	UnitTestPluginUrls
};

class ConfigureSearchResultsBuilderTest extends BaseUnitTest {

	private array $optionDefs;
	private array $landingViewData;

	protected function setUp() :void {
		parent::setUp();

		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'sanitize_key' )->alias(
			static fn( $text ) :string => \is_string( $text )
				? ( \preg_replace( '/[^a-z0-9_-]/', '', \strtolower( \trim( $text ) ) ) ?? '' )
				: ''
		);
		Functions\when( 'sanitize_text_field' )->alias(
			static fn( $text ) :string => \is_string( $text ) ? \trim( $text ) : ''
		);
		Functions\when( 'rawurlencode_deep' )->alias(
			static function ( $value ) {
				if ( \is_array( $value ) ) {
					return \array_map(
						static fn( $item ) :string => \rawurlencode( (string)$item ),
						$value
					);
				}
				return \rawurlencode( (string)$value );
			}
		);
		Functions\when( 'add_query_arg' )->alias(
			static function ( array $params, string $url ) :string {
				if ( empty( $params ) ) {
					return $url;
				}

				$pairs = [];
				foreach ( $params as $key => $value ) {
					$pairs[] = $key.'='.$value;
				}

				return $url.( \str_contains( $url, '?' ) ? '&' : '?' ).\implode( '&', $pairs );
			}
		);

		$this->optionDefs = [
			'custom_silentcaptcha_toggle' => [
				'section'         => 'section_silentcaptcha',
				'name'            => 'Bot Challenge Toggle',
				'summary'         => 'silentCAPTCHA settings switch',
				'description'     => [ 'Enable silentCAPTCHA checks for comment flows.' ],
				'zone_comp_slugs' => [ 'silentcaptcha_component' ],
			],
			'comments_cooldown' => [
				'section'         => 'section_bot_comment_spam_common',
				'zone_comp_slugs' => [ 'module_spam' ],
			],
			'comments_cooldown_shadow' => [
				'section'         => 'section_bot_comment_spam_common',
				'name'            => 'Cooldown Shadow',
				'summary'         => 'Should not be claimed by an explicit option_keys row',
				'description'     => [ 'Explicit option ownership must not expand through the module slug.' ],
				'zone_comp_slugs' => [ 'module_spam' ],
			],
			'orphan_search_target' => [
				'section'         => 'section_defaults',
				'name'            => 'Orphan Search Target',
				'summary'         => 'Should not be shown',
				'description'     => [ 'No configure diagnosis row owns this option.' ],
				'zone_comp_slugs' => [ 'orphan_component' ],
			],
			'block_aggressive' => [
				'section'         => 'section_firewall_blocking_options',
				'name'            => 'Aggressive Scan',
				'summary'         => 'Aggressively Block Data',
				'description'     => [ 'Employs a set of aggressive rules to detect and block malicious data submitted to your site.' ],
				'zone_comp_slugs' => [ 'web_application_firewall', 'module_firewall' ],
			],
			'block_send_email' => [
				'section'         => 'section_firewall_blocking_options',
				'name'            => 'Send Email Report',
				'summary'         => 'Send Firewall Trigger Report Email',
				'description'     => [ 'Send firewall trigger report email.' ],
				'zone_comp_slugs' => [ 'web_application_firewall', 'module_firewall' ],
			],
			'hidden_shared_firewall_option' => [
				'section'         => 'section_firewall_blocking_options',
				'name'            => 'Hidden Shared Firewall Option',
				'summary'         => 'Shared owner must not fall back to general module rows',
				'description'     => [ 'This option has a specific owner that is not visible in Configure diagnosis rows.' ],
				'zone_comp_slugs' => [ 'missing_firewall_component', 'module_firewall' ],
			],
			'disable_xmlrpc' => [
				'section'         => 'section_apixml',
				'zone_comp_slugs' => [ 'xml_rpc_component' ],
			],
			'track_xmlrpc' => [
				'section'         => 'section_bot_behaviours',
				'name'            => 'XML-RPC Access',
				'summary'         => 'Identify A Bot When It Accesses XML-RPC',
				'description'     => [ 'Detect bot-style access to the XML-RPC endpoint.' ],
				'zone_comp_slugs' => [ 'bot_actions', 'module_ips' ],
			],
			'frequency_alert' => [
				'section'         => 'section_reporting',
				'name'            => 'Alert Reporting Frequency',
				'summary'         => 'How often alert reports are sent',
				'description'     => [ 'Configure how frequently alert reports are delivered.' ],
				'zone_comp_slugs' => [ 'reporting', 'instant_alerts' ],
			],
			'instant_alert_admins' => [
				'section'         => 'section_alerts',
				'name'            => 'Instant Alerts For Admins',
				'summary'         => 'Send immediate alerts to admins',
				'description'     => [ 'Choose which admin alerts should be sent instantly.' ],
				'zone_comp_slugs' => [ 'instant_alerts', 'reporting' ],
			],
			'enable_email_authentication' => [
				'section'         => 'section_2fa_email',
				'name'            => 'Email Authentication',
				'summary'         => 'Use email-based login verification',
				'description'     => [ 'Require email authentication during login.' ],
				'zone_comp_slugs' => [ 'two_factor_auth', 'module_login' ],
			],
			'enable_passkeys' => [
				'section'         => 'section_2fa_passkeys',
				'name'            => 'Passkeys',
				'summary'         => 'Enable passkey login verification',
				'description'     => [ 'Allow passkeys as part of login verification.' ],
				'zone_comp_slugs' => [ 'two_factor_auth', 'module_login' ],
			],
			'enable_user_login_email_notification' => [
				'section'         => 'section_user_session_management',
				'name'            => 'User Login Notification Email',
				'summary'         => 'Send Email Notification To Each User Upon Successful Login',
				'description'     => [ 'Send a successful-login email to each user.' ],
				'zone_comp_slugs' => [ 'session_theft_protection', 'module_login' ],
			],
			'user_auto_recover' => [
				'section'         => 'section_auto_black_list',
				'zone_comp_slugs' => [ 'auto_ip_blocking', 'module_ips' ],
			],
			'request_whitelist' => [
				'section'         => 'section_auto_black_list',
				'zone_comp_slugs' => [ 'auto_ip_blocking', 'module_ips' ],
			],
			'cs_enroll_id' => [
				'section'         => 'section_crowdsec',
				'zone_comp_slugs' => [ 'crowdsec_blocking', 'module_ips' ],
			],
			'enable_password_policies' => [
				'section'         => 'section_passwords',
				'zone_comp_slugs' => [ 'password_policies', 'pwned_passwords', 'password_strength', 'module_users' ],
			],
			'pass_prevent_pwned' => [
				'section'         => 'section_passwords',
				'zone_comp_slugs' => [ 'pwned_passwords', 'module_users' ],
			],
			'pass_min_strength' => [
				'section'         => 'section_passwords',
				'zone_comp_slugs' => [ 'password_strength', 'module_users' ],
			],
			'manual_suspend' => [
				'section'         => 'section_suspend',
				'name'            => 'Allow Manual User Suspension',
				'summary'         => 'Manually Suspend User Accounts To Prevent Login',
				'description'     => [ 'Allow administrators to suspend user logins manually.' ],
				'zone_comp_slugs' => [ 'inactive_users', 'module_users' ],
			],
			'auto_password' => [
				'section'         => 'section_suspend',
				'name'            => 'Auto-Suspend Expired Passwords',
				'summary'         => 'Automatically Suspend Users With Expired Passwords',
				'description'     => [ 'Suspend login for users with expired passwords.' ],
				'zone_comp_slugs' => [ 'inactive_users', 'module_users' ],
			],
		];
		$this->landingViewData = $this->landingViewDataFixture();

		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_build_returns_flat_option_and_zone_results_for_silentcaptcha_search() :void {
		$results = $this->newBuilder()->build( 'silentcaptcha' );

		$this->assertNotSame( [], $results );
		$this->assertSame( [ 'zone', 'option' ], \array_column( $results, 'type' ) );
		$this->assertSame(
			[],
			\array_diff( \array_column( $results, 'type' ), [ 'option', 'zone' ] )
		);
		$this->assertSame( 'zone', $results[ 0 ][ 'type' ] ?? '' );
		$this->assertSame(
			$this->landingViewData[ 'tile_lookup' ][ 'spam' ][ 'summary' ],
			$results[ 0 ][ 'summary' ] ?? ''
		);
		$this->assertSame( 'bi bi-shield-fill', $results[ 0 ][ 'icon_class' ] ?? '' );
		$this->assertSame( [
			'key'        => 'spam',
			'label'      => 'Spam',
			'status'     => 'warning',
			'icon_class' => 'bi bi-shield-fill',
			'header'     => [
				'title' => 'Spam',
			],
		], \json_decode( (string)( $results[ 0 ][ 'selection_json' ] ?? '' ), true ) );
		$this->assertSame( '', $results[ 0 ][ 'focus_request_json' ] ?? 'missing' );
		$this->assertSame( 'option', $results[ 1 ][ 'type' ] ?? '' );
		$this->assertSame( 'bi bi-sliders', $results[ 1 ][ 'icon_class' ] ?? '' );
		$this->assertSame( [
			'row_key'     => 'silentcaptcha_component',
			'config_item' => 'custom_silentcaptcha_toggle',
		], \json_decode( (string)( $results[ 1 ][ 'focus_request_json' ] ?? '' ), true ) );
		$this->assertResultHrefQueryMatches( $results[ 1 ], [
			'zone'        => 'spam',
			'row_key'     => 'silentcaptcha_component',
			'config_item' => 'custom_silentcaptcha_toggle',
		] );
	}

	public function test_build_uses_exact_row_keys_and_excludes_unresolvable_options() :void {
		$results = $this->newBuilder()->build( 'comments cooldown orphan shadow' );
		$optionResults = \array_values( \array_filter(
			$results,
			static fn( array $result ) :bool => ( $result[ 'type' ] ?? '' ) === 'option'
		) );

		$this->assertSame(
			[],
			\array_diff( \array_column( $results, 'type' ), [ 'option', 'zone' ] )
		);
		$this->assertNotContains( 'Orphan Search Target', \array_column( $optionResults, 'label' ) );
		$this->assertNotContains( 'Cooldown Shadow', \array_column( $optionResults, 'label' ) );
		$this->assertResultHrefQueryMatches( $optionResults[ 0 ], [
			'zone'        => 'spam',
			'row_key'     => 'general_settings',
			'config_item' => 'comments_cooldown',
		] );
		$this->assertStringNotContainsString( 'expand_id=', $optionResults[ 0 ][ 'href' ] ?? '' );
		$this->assertStringNotContainsString( 'zone_component_slug=', $optionResults[ 0 ][ 'href' ] ?? '' );
		$this->assertStringNotContainsString( 'option_keys=', $optionResults[ 0 ][ 'href' ] ?? '' );
	}

	public function test_shared_options_prefer_specific_component_rows_over_module_rows() :void {
		$results = $this->newBuilder()->build( 'aggressive email report' );
		$optionResults = [];
		foreach ( $results as $result ) {
			if ( ( $result[ 'type' ] ?? '' ) === 'option' ) {
				$optionResults[ $result[ 'label' ] ?? '' ] = $result;
			}
		}

		$this->assertResultHrefQueryMatches( $optionResults[ 'Aggressive Scan' ], [
			'zone'        => 'firewall',
			'row_key'     => 'web_application_firewall',
			'config_item' => 'block_aggressive',
		] );
		$this->assertSame(
			[
				'row_key'     => 'web_application_firewall',
				'config_item' => 'block_aggressive',
			],
			\json_decode( (string)( $optionResults[ 'Aggressive Scan' ][ 'focus_request_json' ] ?? '' ), true )
		);
		$this->assertResultHrefQueryMatches( $optionResults[ 'Send Email Report' ], [
			'zone'        => 'firewall',
			'row_key'     => 'web_application_firewall',
			'config_item' => 'block_send_email',
		] );
		$this->assertArrayNotHasKey(
			'Hidden Shared Firewall Option',
			$optionResults,
			'Shared-owner options must not fall back to general module rows when their specific owner is not visible.'
		);
	}

	public function test_zone_search_matches_authored_tile_summary_text() :void {
		$results = $this->newBuilder()->build( 'stable firewall' );

		$this->assertNotSame( [], $results );
		$this->assertSame( 'zone', $results[ 0 ][ 'type' ] ?? '' );
		$this->assertSame(
			$this->landingViewData[ 'tile_lookup' ][ 'firewall' ][ 'summary' ],
			$results[ 0 ][ 'summary' ] ?? ''
		);
	}

	public function test_hyphenated_option_queries_match_compact_and_split_dash_terms() :void {
		$xmlRpcResults = $this->newBuilder()->build( 'xml-rpc' );
		$xmlRpcCompactResults = $this->newBuilder()->build( 'xmlrpc' );

		$xmlRpcResult = $this->findOptionResultByConfigItem( $xmlRpcResults, 'disable_xmlrpc' );
		$xmlRpcCompactResult = $this->findOptionResultByConfigItem( $xmlRpcCompactResults, 'disable_xmlrpc' );

		$this->assertNotNull( $xmlRpcResult );
		$this->assertSame( 'option', $xmlRpcResult[ 'type' ] ?? '' );
		$this->assertSame(
			[
				'row_key'     => 'xml_rpc_component',
				'config_item' => 'disable_xmlrpc',
			],
			\json_decode( (string)( $xmlRpcResult[ 'focus_request_json' ] ?? '' ), true )
		);
		$this->assertResultHrefQueryMatches( $xmlRpcResult, [
			'zone'        => 'security',
			'row_key'     => 'xml_rpc_component',
			'config_item' => 'disable_xmlrpc',
		] );

		$this->assertNotNull( $xmlRpcCompactResult );
		$this->assertSame(
			[
				'row_key'     => 'xml_rpc_component',
				'config_item' => 'disable_xmlrpc',
			],
			\json_decode( (string)( $xmlRpcCompactResult[ 'focus_request_json' ] ?? '' ), true )
		);
		$this->assertResultHrefQueryMatches( $xmlRpcCompactResult, [
			'zone'        => 'security',
			'row_key'     => 'xml_rpc_component',
			'config_item' => 'disable_xmlrpc',
		] );
	}

	public function test_reports_and_alert_option_queries_route_to_scoped_rows() :void {
		$reportResult = $this->findOptionResultByConfigItem(
			$this->newBuilder()->build( 'alert reporting frequency' ),
			'frequency_alert'
		);
		$alertResult = $this->findOptionResultByConfigItem(
			$this->newBuilder()->build( 'instant admins alert' ),
			'instant_alert_admins'
		);

		$this->assertNotNull( $reportResult );
		$this->assertSame(
			[
				'row_key'     => 'reporting',
				'config_item' => 'frequency_alert',
			],
			\json_decode( (string)( $reportResult[ 'focus_request_json' ] ?? '' ), true )
		);
		$this->assertResultHrefQueryMatches( $reportResult, [
			'zone'        => 'reports_alerts',
			'row_key'     => 'reporting',
			'config_item' => 'frequency_alert',
		] );

		$this->assertNotNull( $alertResult );
		$this->assertSame(
			[
				'row_key'     => 'instant_alerts',
				'config_item' => 'instant_alert_admins',
			],
			\json_decode( (string)( $alertResult[ 'focus_request_json' ] ?? '' ), true )
		);
		$this->assertResultHrefQueryMatches( $alertResult, [
			'zone'        => 'reports_alerts',
			'row_key'     => 'instant_alerts',
			'config_item' => 'instant_alert_admins',
		] );
	}

	public function test_two_factor_option_queries_route_to_split_rows() :void {
		$emailResult = $this->findOptionResultByConfigItem(
			$this->newBuilder()->build( 'email authentication verification' ),
			'enable_email_authentication'
		);
		$passkeyResult = $this->findOptionResultByConfigItem(
			$this->newBuilder()->build( 'passkeys verification' ),
			'enable_passkeys'
		);

		$this->assertNotNull( $emailResult );
		$this->assertSame(
			[
				'row_key'     => 'two_factor_email',
				'config_item' => 'enable_email_authentication',
			],
			\json_decode( (string)( $emailResult[ 'focus_request_json' ] ?? '' ), true )
		);
		$this->assertResultHrefQueryMatches( $emailResult, [
			'zone'        => 'login',
			'row_key'     => 'two_factor_email',
			'config_item' => 'enable_email_authentication',
		] );

		$this->assertNotNull( $passkeyResult );
		$this->assertSame(
			[
				'row_key'     => 'two_factor_otp_passkeys',
				'config_item' => 'enable_passkeys',
			],
			\json_decode( (string)( $passkeyResult[ 'focus_request_json' ] ?? '' ), true )
		);
		$this->assertResultHrefQueryMatches( $passkeyResult, [
			'zone'        => 'login',
			'row_key'     => 'two_factor_otp_passkeys',
			'config_item' => 'enable_passkeys',
		] );
	}

	public function test_ips_and_users_option_queries_route_to_retagged_rows() :void {
		$userAutoRecoverResult = $this->findOptionResultByConfigItem(
			$this->newBuilder()->build( 'auto unblock visitor' ),
			'user_auto_recover'
		);
		$requestWhitelistResult = $this->findOptionResultByConfigItem(
			$this->newBuilder()->build( 'request path whitelist' ),
			'request_whitelist'
		);
		$crowdsecEnrollResult = $this->findOptionResultByConfigItem(
			$this->newBuilder()->build( 'crowdsec enroll id' ),
			'cs_enroll_id'
		);
		$pwnedPasswordsResult = $this->findOptionResultByConfigItem(
			$this->newBuilder()->build( 'prevent pwned passwords' ),
			'pass_prevent_pwned'
		);
		$passwordStrengthResult = $this->findOptionResultByConfigItem(
			$this->newBuilder()->build( 'minimum password strength' ),
			'pass_min_strength'
		);
		$passwordPoliciesResult = $this->findOptionResultByConfigItem(
			$this->newBuilder()->build( 'enforce password policies' ),
			'enable_password_policies'
		);

		$this->assertNotNull( $userAutoRecoverResult );
		$this->assertSame(
			[
				'row_key'     => 'auto_ip_blocking',
				'config_item' => 'user_auto_recover',
			],
			\json_decode( (string)( $userAutoRecoverResult[ 'focus_request_json' ] ?? '' ), true )
		);
		$this->assertResultHrefQueryMatches( $userAutoRecoverResult, [
			'zone'        => 'ips',
			'row_key'     => 'auto_ip_blocking',
			'config_item' => 'user_auto_recover',
		] );

		$this->assertNotNull( $requestWhitelistResult );
		$this->assertSame(
			[
				'row_key'     => 'auto_ip_blocking',
				'config_item' => 'request_whitelist',
			],
			\json_decode( (string)( $requestWhitelistResult[ 'focus_request_json' ] ?? '' ), true )
		);
		$this->assertResultHrefQueryMatches( $requestWhitelistResult, [
			'zone'        => 'ips',
			'row_key'     => 'auto_ip_blocking',
			'config_item' => 'request_whitelist',
		] );

		$this->assertNotNull( $crowdsecEnrollResult );
		$this->assertSame(
			[
				'row_key'     => 'crowdsec_blocking',
				'config_item' => 'cs_enroll_id',
			],
			\json_decode( (string)( $crowdsecEnrollResult[ 'focus_request_json' ] ?? '' ), true )
		);
		$this->assertResultHrefQueryMatches( $crowdsecEnrollResult, [
			'zone'        => 'ips',
			'row_key'     => 'crowdsec_blocking',
			'config_item' => 'cs_enroll_id',
		] );

		$this->assertNotNull( $pwnedPasswordsResult );
		$this->assertSame(
			[
				'row_key'     => 'pwned_passwords',
				'config_item' => 'pass_prevent_pwned',
			],
			\json_decode( (string)( $pwnedPasswordsResult[ 'focus_request_json' ] ?? '' ), true )
		);
		$this->assertResultHrefQueryMatches( $pwnedPasswordsResult, [
			'zone'        => 'users',
			'row_key'     => 'pwned_passwords',
			'config_item' => 'pass_prevent_pwned',
		] );

		$this->assertNotNull( $passwordStrengthResult );
		$this->assertSame(
			[
				'row_key'     => 'password_strength',
				'config_item' => 'pass_min_strength',
			],
			\json_decode( (string)( $passwordStrengthResult[ 'focus_request_json' ] ?? '' ), true )
		);
		$this->assertResultHrefQueryMatches( $passwordStrengthResult, [
			'zone'        => 'users',
			'row_key'     => 'password_strength',
			'config_item' => 'pass_min_strength',
		] );

		$this->assertNotNull( $passwordPoliciesResult );
		$this->assertSame(
			[
				'row_key'     => 'password_policies',
				'config_item' => 'enable_password_policies',
			],
			\json_decode( (string)( $passwordPoliciesResult[ 'focus_request_json' ] ?? '' ), true )
		);
		$this->assertResultHrefQueryMatches( $passwordPoliciesResult, [
			'zone'        => 'users',
			'row_key'     => 'password_policies',
			'config_item' => 'enable_password_policies',
		] );
	}

	public function test_session_suspension_and_bot_signal_queries_route_to_correct_rows() :void {
		$trackXmlRpcResult = $this->findOptionResultByConfigItem(
			$this->newBuilder()->build( 'xml-rpc bot action' ),
			'track_xmlrpc'
		);
		$loginNotificationResult = $this->findOptionResultByConfigItem(
			$this->newBuilder()->build( 'login notification email' ),
			'enable_user_login_email_notification'
		);
		$manualSuspendResult = $this->findOptionResultByConfigItem(
			$this->newBuilder()->build( 'manual user suspension' ),
			'manual_suspend'
		);
		$autoPasswordResult = $this->findOptionResultByConfigItem(
			$this->newBuilder()->build( 'expired password suspension' ),
			'auto_password'
		);

		$this->assertNotNull( $trackXmlRpcResult );
		$this->assertResultHrefQueryMatches( $trackXmlRpcResult, [
			'zone'        => 'ips',
			'row_key'     => 'bot_actions',
			'config_item' => 'track_xmlrpc',
		] );

		$this->assertNotNull( $loginNotificationResult );
		$this->assertResultHrefQueryMatches( $loginNotificationResult, [
			'zone'        => 'login',
			'row_key'     => 'session_theft_protection',
			'config_item' => 'enable_user_login_email_notification',
		] );

		$this->assertNotNull( $manualSuspendResult );
		$this->assertResultHrefQueryMatches( $manualSuspendResult, [
			'zone'        => 'users',
			'row_key'     => 'inactive_users',
			'config_item' => 'manual_suspend',
		] );

		$this->assertNotNull( $autoPasswordResult );
		$this->assertResultHrefQueryMatches( $autoPasswordResult, [
			'zone'        => 'users',
			'row_key'     => 'inactive_users',
			'config_item' => 'auto_password',
		] );
	}

	private function newBuilder() :ConfigureSearchResultsBuilder {
		return new ConfigureSearchResultsBuilder(
			new class( $this->landingViewData ) extends ConfigureLandingViewBuilder {
				private array $landingViewData;

				public function __construct( array $landingViewData ) {
					$this->landingViewData = $landingViewData;
				}

				public function build() :array {
					return $this->landingViewData;
				}
			}
		);
	}

	private function landingViewDataFixture() :array {
		return [
			'tile_lookup' => [
				'spam' => [
					'summary' => 'Stable spam summary.',
				],
				'firewall' => [
					'summary' => 'Stable firewall summary.',
				],
				'security' => [
					'summary' => 'Stable security summary.',
				],
				'reports_alerts' => [
					'summary' => 'Stable reports and alerts summary.',
				],
				'login' => [
					'summary' => 'Stable login summary.',
				],
				'ips' => [
					'summary' => 'Stable bots and IPs summary.',
				],
				'users' => [
					'summary' => 'Stable user protection summary.',
				],
			],
			'diagnoses' => [
				'spam' => [
					'zone_key'      => 'spam',
					'zone_label'    => 'Spam',
					'zone_icon_class' => 'bi bi-shield-fill',
					'zone_selection_json' => \json_encode( [
						'key'        => 'spam',
						'label'      => 'Spam',
						'status'     => 'warning',
						'icon_class' => 'bi bi-shield-fill',
						'header'     => [
							'title' => 'Spam',
						],
					], JSON_THROW_ON_ERROR ),
					'preview_text'  => 'Review silentCAPTCHA settings and comment protection.',
					'risk_context'  => 'Spam settings protect comment workflows.',
					'problem_rows'  => [
						[
							'key'           => 'silentcaptcha_component',
							'title'         => 'silentCAPTCHA Protection',
							'summary'       => 'Configure silentCAPTCHA coverage.',
							'explanations'  => [ 'silentCAPTCHA settings help block comment bots.' ],
							'expand_action' => [
								'id'              => 'configure-diagnosis-spam-silentcaptcha_component',
								'is_expandable'   => true,
								'data_attributes' => [
									'zone_component_slug' => 'silentcaptcha_component',
									'config_item'         => 'custom_silentcaptcha_toggle',
								],
							],
						],
					],
					'review_rows'   => [
						[
							'key'           => 'general_settings',
							'title'         => 'Comment Cooldown',
							'summary'       => 'Adjust comment throttling.',
							'explanations'  => [ 'Cooldown settings reduce repeated comment submissions.' ],
							'expand_action' => [
								'id'              => 'configure-diagnosis-spam-general_settings',
								'is_expandable'   => true,
								'data_attributes' => [
									'zone_component_slug' => 'module_spam',
									'option_keys'         => 'comments_cooldown',
								],
							],
						],
					],
					'healthy_rows'  => [],
				],
				'firewall' => [
					'zone_key'      => 'firewall',
					'zone_label'    => 'Firewall',
					'zone_icon_class' => 'bi bi-fire',
					'zone_selection_json' => \json_encode( [
						'key'        => 'firewall',
						'label'      => 'Firewall',
						'status'     => 'good',
						'icon_class' => 'bi bi-fire',
						'header'     => [
							'title' => 'Firewall',
						],
					], JSON_THROW_ON_ERROR ),
					'preview_text'  => 'Review firewall controls.',
					'risk_context'  => 'Firewall settings protect request flows.',
					'problem_rows'  => [],
					'review_rows'   => [
						[
							'key'           => 'general_settings',
							'title'         => 'Firewall General Settings',
							'summary'       => 'Adjust module-only firewall settings.',
							'explanations'  => [ 'General firewall settings.' ],
							'expand_action' => [
								'id'              => 'configure-diagnosis-firewall-general_settings',
								'is_expandable'   => true,
								'data_attributes' => [
									'zone_component_slug' => 'module_firewall',
								],
							],
						],
					],
					'healthy_rows'  => [
						[
							'key'           => 'web_application_firewall',
							'title'         => 'Web Application Firewall',
							'summary'       => 'Configure WAF rules.',
							'explanations'  => [ 'Aggressive WAF rules block bad requests.' ],
							'expand_action' => [
								'id'              => 'configure-diagnosis-firewall-web_application_firewall',
								'is_expandable'   => true,
								'data_attributes' => [
									'zone_component_slug' => 'web_application_firewall',
								],
							],
						],
					],
				],
				'security' => [
					'zone_key'      => 'security',
					'zone_label'    => 'Security',
					'zone_icon_class' => 'bi bi-lock-fill',
					'zone_selection_json' => \json_encode( [
						'key'        => 'security',
						'label'      => 'Security',
						'status'     => 'warning',
						'icon_class' => 'bi bi-lock-fill',
						'header'     => [
							'title' => 'Security',
						],
					], JSON_THROW_ON_ERROR ),
					'preview_text'  => 'Review API and XML-RPC controls.',
					'risk_context'  => 'Security settings protect exposed WordPress system interfaces.',
					'problem_rows'  => [],
					'review_rows'   => [
						[
							'key'           => 'xml_rpc_component',
							'title'         => 'XML-RPC Controls',
							'summary'       => 'Review XML-RPC hardening.',
							'explanations'  => [ 'Disable XML-RPC when it is not required.' ],
							'expand_action' => [
								'id'              => 'configure-diagnosis-security-xml_rpc_component',
								'is_expandable'   => true,
								'data_attributes' => [
									'zone_component_slug' => 'xml_rpc_component',
									'config_item'         => 'disable_xmlrpc',
								],
							],
						],
					],
					'healthy_rows'  => [],
				],
				'reports_alerts' => [
					'zone_key'      => 'reports_alerts',
					'zone_label'    => 'Reports & Alerts',
					'zone_icon_class' => 'bi bi-bell-fill',
					'zone_selection_json' => \json_encode( [
						'key'        => 'reports_alerts',
						'label'      => 'Reports & Alerts',
						'status'     => 'neutral',
						'icon_class' => 'bi bi-bell-fill',
						'header'     => [
							'title' => 'Reports & Alerts',
						],
					], JSON_THROW_ON_ERROR ),
					'preview_text'  => 'Review reporting and alert delivery settings.',
					'risk_context'  => 'Reports and alerts keep operators informed about plugin activity.',
					'problem_rows'  => [],
					'review_rows'   => [
						[
							'key'           => 'reporting',
							'title'         => 'Reports',
							'summary'       => 'Configure report delivery settings.',
							'explanations'  => [ 'Control scheduled reporting frequency.' ],
							'expand_action' => [
								'id'              => 'configure-diagnosis-reports_alerts-reporting',
								'is_expandable'   => true,
								'data_attributes' => [
									'zone_component_slug' => 'reporting',
									'option_keys'         => 'frequency_alert',
								],
							],
						],
						[
							'key'           => 'instant_alerts',
							'title'         => 'Instant Alerts',
							'summary'       => 'Configure immediate alert delivery.',
							'explanations'  => [ 'Send high-signal alerts to admins right away.' ],
							'expand_action' => [
								'id'              => 'configure-diagnosis-reports_alerts-instant_alerts',
								'is_expandable'   => true,
								'data_attributes' => [
									'zone_component_slug' => 'instant_alerts',
									'option_keys'         => 'instant_alert_admins',
								],
							],
						],
					],
					'healthy_rows'  => [],
				],
				'login' => [
					'zone_key'      => 'login',
					'zone_label'    => 'Login',
					'zone_icon_class' => 'bi bi-person-lock',
					'zone_selection_json' => \json_encode( [
						'key'        => 'login',
						'label'      => 'Login',
						'status'     => 'warning',
						'icon_class' => 'bi bi-person-lock',
						'header'     => [
							'title' => 'Login',
						],
					], JSON_THROW_ON_ERROR ),
					'preview_text'  => 'Review login verification settings.',
					'risk_context'  => 'Login settings protect authentication flows.',
					'problem_rows'  => [
						[
							'key'           => 'two_factor_general',
							'title'         => '2FA General Settings',
							'summary'       => 'Review core two-factor settings.',
							'explanations'  => [ 'Require strong two-factor coverage.' ],
							'expand_action' => [
								'id'              => 'configure-diagnosis-login-two_factor_general',
								'is_expandable'   => true,
								'data_attributes' => [
									'zone_component_slug' => 'two_factor_auth',
									'option_keys'         => 'mfa_verify_page,allow_backupcodes',
								],
							],
						],
					],
					'review_rows'   => [
						[
							'key'           => 'two_factor_email',
							'title'         => 'Email Authentication',
							'summary'       => 'Configure email-based login verification.',
							'explanations'  => [ 'Use email authentication where it fits your login flow.' ],
							'expand_action' => [
								'id'              => 'configure-diagnosis-login-two_factor_email',
								'is_expandable'   => true,
								'data_attributes' => [
									'zone_component_slug' => 'two_factor_auth',
									'option_keys'         => 'enable_email_authentication',
								],
							],
						],
						[
							'key'           => 'two_factor_otp_passkeys',
							'title'         => 'OTP & Passkeys',
							'summary'       => 'Configure authenticator apps and passkeys.',
							'explanations'  => [ 'Passkeys and OTP apps strengthen login verification.' ],
							'expand_action' => [
								'id'              => 'configure-diagnosis-login-two_factor_otp_passkeys',
								'is_expandable'   => true,
								'data_attributes' => [
									'zone_component_slug' => 'two_factor_auth',
									'option_keys'         => 'enable_passkeys',
								],
							],
						],
						[
							'key'           => 'session_theft_protection',
							'title'         => 'Session Hijacking Protection',
							'summary'       => 'Configure session lock-down and login notifications.',
							'explanations'  => [ 'Session rules protect active user logins.' ],
							'expand_action' => [
								'id'              => 'configure-diagnosis-login-session_theft_protection',
								'is_expandable'   => true,
								'data_attributes' => [
									'zone_component_slug' => 'session_theft_protection',
									'option_keys'         => 'enable_user_login_email_notification',
								],
							],
						],
					],
					'healthy_rows'  => [],
				],
				'ips' => [
					'zone_key'      => 'ips',
					'zone_label'    => 'Bots & IPs',
					'zone_icon_class' => 'bi bi-robot',
					'zone_selection_json' => \json_encode( [
						'key'        => 'ips',
						'label'      => 'Bots & IPs',
						'status'     => 'warning',
						'icon_class' => 'bi bi-robot',
						'header'     => [
							'title' => 'Bots & IPs',
						],
					], JSON_THROW_ON_ERROR ),
					'preview_text'  => 'Review automatic IP blocking, CrowdSec, and bot handling.',
					'risk_context'  => 'IP settings block repeat offenders and known malicious visitors.',
					'problem_rows'  => [
						[
							'key'           => 'auto_ip_blocking',
							'title'         => 'Automatic IP Blocking',
							'summary'       => 'Configure automatic blocking and recovery rules.',
							'explanations'  => [ 'Automatic IP blocking limits repeat offenders.' ],
							'expand_action' => [
								'id'              => 'configure-diagnosis-ips-auto_ip_blocking',
								'is_expandable'   => true,
								'data_attributes' => [
									'zone_component_slug' => 'auto_ip_blocking',
									'option_keys'         => 'user_auto_recover,request_whitelist',
								],
							],
						],
					],
					'review_rows'   => [
						[
							'key'           => 'crowdsec_blocking',
							'title'         => 'CrowdSec IP Blocking',
							'summary'       => 'Configure CrowdSec list handling and enrolment.',
							'explanations'  => [ 'CrowdSec can block known malicious IPs before they trigger local defenses.' ],
							'expand_action' => [
								'id'              => 'configure-diagnosis-ips-crowdsec_blocking',
								'is_expandable'   => true,
								'data_attributes' => [
									'zone_component_slug' => 'crowdsec_blocking',
									'option_keys'         => 'cs_enroll_id',
								],
							],
						],
						[
							'key'           => 'bot_actions',
							'title'         => 'Bot Actions',
							'summary'       => 'Control how repeated bot behaviour is handled.',
							'explanations'  => [ 'Bot actions decide when suspicious requests trigger penalties.' ],
							'expand_action' => [
								'id'              => 'configure-diagnosis-ips-bot_actions',
								'is_expandable'   => true,
								'data_attributes' => [
									'zone_component_slug' => 'bot_actions',
									'option_keys'         => 'track_xmlrpc',
								],
							],
						],
					],
					'healthy_rows'  => [],
				],
				'users' => [
					'zone_key'      => 'users',
					'zone_label'    => 'Users',
					'zone_icon_class' => 'bi bi-person-badge-fill',
					'zone_selection_json' => \json_encode( [
						'key'        => 'users',
						'label'      => 'Users',
						'status'     => 'warning',
						'icon_class' => 'bi bi-person-badge-fill',
						'header'     => [
							'title' => 'Users',
						],
					], JSON_THROW_ON_ERROR ),
					'preview_text'  => 'Review password rules and user account protections.',
					'risk_context'  => 'User settings enforce password and account protection rules.',
					'problem_rows'  => [
						[
							'key'           => 'password_policies',
							'title'         => 'Password Policies',
							'summary'       => 'Enable and review core password policies.',
							'explanations'  => [ 'Password policies apply the configured password restrictions.' ],
							'expand_action' => [
								'id'              => 'configure-diagnosis-users-password_policies',
								'is_expandable'   => true,
								'data_attributes' => [
									'zone_component_slug' => 'password_policies',
									'option_keys'         => 'enable_password_policies',
									'config_item'         => 'enable_password_policies',
								],
							],
						],
					],
					'review_rows'   => [
						[
							'key'           => 'pwned_passwords',
							'title'         => 'Block Pwned Passwords',
							'summary'       => 'Prevent compromised passwords from being used.',
							'explanations'  => [ 'Pwned password checks block known compromised passwords.' ],
							'expand_action' => [
								'id'              => 'configure-diagnosis-users-pwned_passwords',
								'is_expandable'   => true,
								'data_attributes' => [
									'zone_component_slug' => 'pwned_passwords',
									'option_keys'         => 'enable_password_policies,pass_prevent_pwned',
									'config_item'         => 'pass_prevent_pwned',
								],
							],
						],
						[
							'key'           => 'password_strength',
							'title'         => 'Enforce Minimum Password Strength',
							'summary'       => 'Require stronger passwords.',
							'explanations'  => [ 'Minimum password strength rules prevent weak credentials.' ],
							'expand_action' => [
								'id'              => 'configure-diagnosis-users-password_strength',
								'is_expandable'   => true,
								'data_attributes' => [
									'zone_component_slug' => 'password_strength',
									'option_keys'         => 'enable_password_policies,pass_min_strength',
									'config_item'         => 'pass_min_strength',
								],
							],
						],
						[
							'key'           => 'inactive_users',
							'title'         => 'User Suspension',
							'summary'       => 'Configure manual and automatic user suspension.',
							'explanations'  => [ 'User suspension limits access for risky or stale accounts.' ],
							'expand_action' => [
								'id'              => 'configure-diagnosis-users-inactive_users',
								'is_expandable'   => true,
								'data_attributes' => [
									'zone_component_slug' => 'inactive_users',
									'option_keys'         => 'manual_suspend,auto_password',
								],
							],
						],
					],
					'healthy_rows'  => [],
				],
			],
		];
	}

	private function installControllerStub() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new class extends UnitTestPluginUrls {
			public function cfgForZoneComponent( string $slug ) :string {
				return '/admin/config/'.$slug;
			}
		};
		$controller->labels = new class {
			public string $Name = 'Shield';

			public function getBrandName( string $brand ) :string {
				return $brand === 'silentcaptcha' ? 'silentCAPTCHA' : $brand;
			}
		};
		$controller->svgs = new class {
			public function iconClass( string $icon ) :string {
				return 'bi bi-'.$icon;
			}
		};
		$controller->cfg = (object)[
			'configuration' => (object)[
				'options'  => $this->optionDefs,
				'sections' => [],
			],
		];
		$controller->comps = (object)[
			'license'     => new class {
				public function hasValidWorkingLicense() :bool {
					return false;
				}
			},
			'crowdsec'    => new class {
				public function getCApiStore() {
					return new class {
						public function retrieveMachineId() :string {
							return '';
						}
					};
				}
			},
			'opts_lookup' => new class {
				public function getReportEmail() :string {
					return 'reports@example.com';
				}
			},
		];
		$controller->opts = new class( $this->optionDefs ) {
			private array $optionDefs;

			public function __construct( array $optionDefs ) {
				$this->optionDefs = $optionDefs;
			}

			public function optGet( string $key ) {
				return null;
			}

			public function optDefault( string $key ) {
				return match ( $key ) {
					'transgression_limit' => 10,
					default => null,
				};
			}

			public function optDef( string $key ) :array {
				return $this->optionDefs[ $key ] ?? [];
			}
		};

		PluginControllerInstaller::install( $controller );
	}

	private function findOptionResultByConfigItem( array $results, string $configItem ) :?array {
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

	private function assertResultHrefQueryMatches( array $result, array $expectedQuery ) :void {
		$href = (string)( $result[ 'href' ] ?? '' );
		$this->assertNotSame( '', $href );

		$query = (string)( \parse_url( $href, \PHP_URL_QUERY ) ?? '' );
		parse_str( $query, $queryArgs );

		foreach ( $expectedQuery as $key => $value ) {
			$this->assertSame( $value, (string)( $queryArgs[ $key ] ?? '' ) );
		}
	}
}
