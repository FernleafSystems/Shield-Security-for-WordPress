<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Zones\Component;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Utility\FileLockKeyApplicability;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component\{
	Base as ComponentBase,
	BotActions,
	CommentSpamBlockBot,
	CommentSpamBlockHuman,
	ContactFormSpamBlockBot,
	CrowdsecBlocking,
	FileEditingBlock,
	FileLocker,
	FileScanning,
	InactiveUsers,
	LoginHide,
	LoginProtectionForms,
	PasswordPolicies,
	PasswordStrength,
	PwnedPasswords,
	RateLimiting,
	ScanScheduling,
	SecadminEnabled,
	SecadminWpAdmins,
	SecadminWpOptions,
	SessionTheftProtection,
	SilentCaptcha,
	SpamUserRegisterBlock,
	TrustedCommenters,
	TwoFactorAuth,
	UsernameFishingBlock,
	VulnerabilityScanning,
	WebApplicationFirewall,
	XmlRpcDisable
};
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone\Firewall as FirewallZone;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone\Login as LoginZone;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone\Users as UsersZone;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\SecurityZonesCon;

class ConfigureSeverityAssessmentTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		$_SERVER[ 'HTTP_USER_AGENT' ] = 'Unit Test Agent';

		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'esc_html' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'sanitize_key' )->alias(
			static fn( $text ) :string => \is_string( $text )
				? ( \preg_replace( '/[^a-z0-9_-]/', '', \strtolower( \trim( $text ) ) ) ?? '' )
				: ''
		);
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_simple_warning_rows_are_downgraded_in_configure_only() :void {
		$cases = [
			'username fishing'    => [ fn() => new UsernameFishingBlock(), [ 'block_author_discovery' => 'N' ], [] ],
			'xml-rpc'             => [ fn() => new XmlRpcDisable(), [ 'disable_xmlrpc' => 'N' ], [] ],
			'rate limiting'       => [ fn() => new RateLimiting(), [], [ 'opts_lookup' => $this->buildOptsLookupStub( [ 'enabledTrafficLimiter' => false ] ) ] ],
			'file editing'        => [ fn() => new FileEditingBlock(), [ 'disable_file_editing' => 'N' ], [] ],
			'crowdsec'            => [ fn() => new CrowdsecBlocking(), [], [ 'opts_lookup' => $this->buildOptsLookupStub( [ 'enabledCrowdSecAutoBlock' => false ] ) ] ],
			'inactive users'      => [ fn() => new InactiveUsers(), [], [ 'user_suspend' => new class {
				public function isSuspendManualEnabled() :bool {
					return false;
				}

				public function isSuspendAutoIdleEnabled() :bool {
					return false;
				}

				public function isSuspendAutoPasswordEnabled() :bool {
					return false;
				}
			} ] ],
			'spam registration'   => [ fn() => new SpamUserRegisterBlock(), [], [ 'opts_lookup' => $this->buildOptsLookupStub( [ 'getEmailValidateChecks' => [] ] ) ] ],
			'comment spam bot'    => [ fn() => new CommentSpamBlockBot(), [], [ 'opts_lookup' => $this->buildOptsLookupStub( [ 'enabledSilentCaptchaCommentSpam' => false ] ) ] ],
			'comment spam human'  => [ fn() => new CommentSpamBlockHuman(), [], [ 'opts_lookup' => $this->buildOptsLookupStub( [ 'enabledHumanCommentSpam' => false ] ) ] ],
			'contact form spam'   => [ fn() => new ContactFormSpamBlockBot(), [], [ 'forms_spam' => new class { public function getInstalled() :array { return [ FakeSpamForm::class ]; } } ] ],
			'trusted commenters'  => [ fn() => new TrustedCommenters(), [], [ 'opts_lookup' => $this->buildOptsLookupStub( [ 'getCommenterTrustedMinimum' => 1 ] ) ] ],
		];

		foreach ( $cases as $label => [ $factory, $optValues, $componentOverrides ] ) {
			$this->installController( $optValues, $componentOverrides );
			$row = $this->firstConfigureRow( $factory() );
			$this->assertSame( EnumEnabledStatus::OKAY, $row[ 'enabled_status' ] ?? null, $label.' should downgrade to warning in Configure' );
		}
	}

	public function test_critical_rows_remain_critical_in_configure() :void {
		$cases = [
			'security admin'         => [ fn() => new SecadminEnabled(), [], [ 'sec_admin' => new class { public function isEnabledSecAdmin() :bool { return false; } } ] ],
			'security admin options' => [ fn() => new SecadminWpOptions(), [ 'admin_access_restrict_options' => 'N' ], [ 'sec_admin' => new class { public function isEnabledSecAdmin() :bool { return false; } } ] ],
			'security admin admins'  => [ fn() => new SecadminWpAdmins(), [ 'admin_access_restrict_admin_users' => 'N' ], [ 'sec_admin' => new class { public function isEnabledSecAdmin() :bool { return false; } } ] ],
			'password policies'      => [ fn() => new PasswordPolicies(), [], [ 'opts_lookup' => $this->buildOptsLookupStub( [ 'isPassPoliciesEnabled' => false ] ) ] ],
			'password strength'      => [ fn() => new PasswordStrength(), [ 'enable_password_policies' => 'N', 'pass_min_strength' => 2 ], [] ],
			'pwned passwords'        => [ fn() => new PwnedPasswords(), [ 'enable_password_policies' => 'N', 'pass_prevent_pwned' => 'N' ], [] ],
		];

		foreach ( $cases as $label => [ $factory, $optValues, $componentOverrides ] ) {
			$this->installController( $optValues, $componentOverrides );
			$row = $this->firstConfigureRow( $factory() );
			$this->assertSame( EnumEnabledStatus::BAD, $row[ 'enabled_status' ] ?? null, $label.' should remain critical in Configure' );
		}
	}

	public function test_scan_scheduling_downgrades_once_daily_to_warning() :void {
		$this->installController( [ 'scan_frequency' => 1 ] );

		$row = $this->firstConfigureRow( new ScanScheduling() );

		$this->assertSame( EnumEnabledStatus::OKAY, $row[ 'enabled_status' ] ?? null );
		$this->assertCount( 1, $row[ 'explanations' ] ?? [] );
	}

	public function test_file_scanning_uses_shared_scan_and_repair_definitions() :void {
		$this->installController(
			[
				'enable_core_file_integrity_scan' => 'Y',
				'file_scan_areas'                 => [ 'malware_php', 'plugins', 'themes', 'wpcontent', 'wproot' ],
				'file_repair_areas'               => [ 'plugin', 'theme' ],
			],
			[
				'scans' => $this->buildScansComponent( [
					'afsEnabled'     => true,
					'fileScanAreas'  => [ 'malware_php', 'plugins', 'themes', 'wpcontent', 'wproot' ],
					'malwareEnabled' => true,
					'wpCoreEnabled'  => false,
					'pluginsEnabled' => true,
					'themesEnabled'  => true,
					'wpContentEnabled' => true,
					'wpRootEnabled'  => true,
					'repairWp'       => false,
					'repairPlugin'   => true,
					'repairTheme'    => true,
				] ),
			]
		);

		$row = $this->firstConfigureRow( new FileScanning() );

		$this->assertSame( EnumEnabledStatus::OKAY, $row[ 'enabled_status' ] ?? null );
		$this->assertCount( 2, $row[ 'explanations' ] ?? [] );
	}

	public function test_vulnerability_scanning_treats_secondary_gaps_as_warning() :void {
		$this->installController(
			[],
			[
				'scans' => $this->buildScansComponent( [
					'wpvEnabled'            => true,
					'wpvAutoupdatesEnabled' => false,
					'apcEnabled'            => false,
				] ),
			]
		);

		$row = $this->firstConfigureRow( new VulnerabilityScanning() );

		$this->assertSame( EnumEnabledStatus::OKAY, $row[ 'enabled_status' ] ?? null );
		$this->assertCount( 2, $row[ 'explanations' ] ?? [] );
	}

	public function test_file_locker_requires_wpconfig_and_accepts_real_lock_keys() :void {
		$this->installController(
			[],
			[
				'file_locker' => new class {
					public function getFilesToLock() :array {
						return [ 'theme_functions', 'root_htaccess', 'root_index', 'root_webconfig' ];
					}

					public function lastCipherCheckFoundNone() :bool {
						return false;
					}
				},
			]
		);

		$row = $this->firstConfigureRow( $this->fileLockerForEnvironment() );

		$this->assertSame( EnumEnabledStatus::BAD, $row[ 'enabled_status' ] );
		$this->assertCount( 1, $row[ 'explanations' ] );

		$this->installController(
			[],
			[
				'file_locker' => new class {
					public function getFilesToLock() :array {
						return [ 'wpconfig', 'theme_functions', 'root_htaccess', 'root_index', 'root_webconfig' ];
					}

					public function lastCipherCheckFoundNone() :bool {
						return false;
					}
				},
			]
		);

		$row = $this->firstConfigureRow( $this->fileLockerForEnvironment() );

		$this->assertSame( EnumEnabledStatus::GOOD, $row[ 'enabled_status' ] );
		$this->assertSame( [], $row[ 'explanations' ] );
	}

	public function test_file_locker_ignores_non_applicable_webconfig_in_configure() :void {
		$this->installController(
			[],
			[
				'file_locker' => new class {
					public function getFilesToLock() :array {
						return [ 'wpconfig', 'theme_functions', 'root_htaccess', 'root_index' ];
					}

					public function lastCipherCheckFoundNone() :bool {
						return false;
					}
				},
			]
		);

		$fileLocker = $this->fileLockerForEnvironment( false, true );
		$row = $this->firstConfigureRow( $fileLocker );

		$this->assertSame( EnumEnabledStatus::GOOD, $row[ 'enabled_status' ] );
		$this->assertSame( [], $row[ 'explanations' ] );
	}

	public function test_file_locker_ignores_non_applicable_theme_functions_in_configure() :void {
		$this->installController(
			[],
			[
				'file_locker' => new class {
					public function getFilesToLock() :array {
						return [ 'wpconfig', 'root_htaccess', 'root_index', 'root_webconfig' ];
					}

					public function lastCipherCheckFoundNone() :bool {
						return false;
					}
				},
			]
		);

		$fileLocker = $this->fileLockerForEnvironment( true, false );
		$row = $this->firstConfigureRow( $fileLocker );

		$this->assertSame( EnumEnabledStatus::GOOD, $row[ 'enabled_status' ] );
		$this->assertSame( [], $row[ 'explanations' ] );
	}

	public function test_file_locker_applicability_removes_only_non_applicable_known_stored_keys() :void {
		$applicability = new FileLockKeyApplicability( false, false );

		$this->assertSame(
			[ 'wpconfig', 'root_htaccess', 'unknown_file' ],
			$applicability->removeNonApplicableKnownKeys( [
				'wpconfig',
				'root_webconfig',
				'root_htaccess',
				'theme_functions',
				'unknown_file',
			] )
		);
	}

	public function test_waf_only_becomes_critical_when_primary_rules_are_absent() :void {
		$this->installController( [
			'block_dir_traversal'    => 'N',
			'block_sql_queries'      => 'N',
			'block_field_truncation' => 'Y',
			'block_php_code'         => 'N',
			'block_aggressive'       => 'Y',
		] );

		$row = $this->firstConfigureRow( new WebApplicationFirewall() );

		$this->assertSame( EnumEnabledStatus::BAD, $row[ 'enabled_status' ] ?? null );
		$this->assertCount( 4, $row[ 'explanations' ] ?? [] );
	}

	public function test_waf_aggressive_rule_disabled_is_warning_when_primary_rules_are_enabled() :void {
		$this->installController( [
			'block_dir_traversal'    => 'Y',
			'block_sql_queries'      => 'Y',
			'block_field_truncation' => 'Y',
			'block_php_code'         => 'Y',
			'block_aggressive'       => 'N',
		] );

		$row = $this->firstConfigureRow( new WebApplicationFirewall() );

		$this->assertSame( EnumEnabledStatus::OKAY, $row[ 'enabled_status' ] );
		$this->assertCount( 1, $row[ 'explanations' ] );
	}

	public function test_firewall_zone_owns_core_rule_components() :void {
		$this->installController();

		$zone = new FirewallZone();
		$components = $zone->components();

		$this->assertContains( WebApplicationFirewall::class, $components );
		$this->assertContains( UsernameFishingBlock::class, $components );
		$this->assertContains( FileEditingBlock::class, $components );
	}

	public function test_login_zone_owns_login_guard_components() :void {
		$this->installController();

		$zone = new LoginZone();
		$components = $zone->components();

		$this->assertContains( LoginProtectionForms::class, $components );
		$this->assertContains( LoginHide::class, $components );
		$this->assertContains( TwoFactorAuth::class, $components );
		$this->assertContains( SessionTheftProtection::class, $components );
	}

	public function test_users_zone_owns_user_management_components() :void {
		$this->installController();

		$zone = new UsersZone();

		$this->assertSame( [
			PasswordPolicies::class,
			PwnedPasswords::class,
			PasswordStrength::class,
			InactiveUsers::class,
			SpamUserRegisterBlock::class,
		], $zone->components() );
	}

	public function test_firewall_core_component_statuses_follow_options() :void {
		$enabledOptions = [
			'block_dir_traversal'    => 'Y',
			'block_sql_queries'      => 'Y',
			'block_field_truncation' => 'Y',
			'block_php_code'         => 'Y',
			'block_aggressive'       => 'Y',
			'disable_file_editing'   => 'Y',
			'block_author_discovery' => 'Y',
		];
		$this->installController( $enabledOptions );

		$enabledWaf = new WebApplicationFirewall();

		$this->assertSame( EnumEnabledStatus::GOOD, $enabledWaf->enabledStatus() );
		$this->assertSame( EnumEnabledStatus::GOOD, ( new FileEditingBlock() )->enabledStatus() );
		$this->assertSame( EnumEnabledStatus::GOOD, ( new UsernameFishingBlock() )->enabledStatus() );

		$disabledOptions = \array_merge( $enabledOptions, [
			'block_dir_traversal'    => 'N',
			'block_sql_queries'      => 'N',
			'block_field_truncation' => 'N',
			'block_php_code'         => 'N',
			'block_aggressive'       => 'N',
			'disable_file_editing'   => 'N',
			'block_author_discovery' => 'N',
		] );
		$this->installController( $disabledOptions );

		$disabledWaf = new WebApplicationFirewall();

		$this->assertSame( EnumEnabledStatus::BAD, $disabledWaf->enabledStatus() );
		$this->assertSame( EnumEnabledStatus::BAD, ( new FileEditingBlock() )->enabledStatus() );
		$this->assertSame( EnumEnabledStatus::BAD, ( new UsernameFishingBlock() )->enabledStatus() );
	}

	public function test_bot_actions_use_partial_primary_coverage() :void {
		$this->installController( [
			'track_logininvalid'   => 'disabled',
			'track_loginfailed'    => 'block',
			'track_xmlrpc'         => 'log',
			'track_fakewebcrawler' => 'disabled',
			'track_404'            => 'disabled',
			'track_linkcheese'     => 'disabled',
			'track_invalidscript'  => 'disabled',
			'track_useragent'      => 'block',
		] );

		$component = new BotActions();
		$row = $this->firstConfigureRow( $component );

		$this->assertSame( EnumEnabledStatus::OKAY, $row[ 'enabled_status' ] ?? null );
		$this->assertCount( 6, $row[ 'explanations' ] ?? [] );
	}

	public function test_bot_actions_say_offenses_do_nothing_while_the_offense_limit_is_zero() :void {
		$signals = [
			'track_logininvalid'   => 'block',
			'track_loginfailed'    => 'block',
			'track_xmlrpc'         => 'block',
			'track_fakewebcrawler' => 'block',
			'track_404'            => 'block',
			'track_linkcheese'     => 'block',
			'track_invalidscript'  => 'block',
			'track_useragent'      => 'block',
		];
		$this->installController( $signals );
		$this->assertSame( EnumEnabledStatus::GOOD, $this->firstConfigureRow( new BotActions() )[ 'enabled_status' ] );

		$this->installController( $signals, [ 'opts_lookup' => $this->buildOptsLookupStub( [ 'enabledIpAutoBlock' => false ] ) ] );
		$row = $this->firstConfigureRow( new BotActions() );

		$this->assertSame( EnumEnabledStatus::OKAY, $row[ 'enabled_status' ] );
		$this->assertSame( [ 'Responses that record an offense have no effect while the offense limit is 0.' ], $row[ 'explanations' ] );
	}

	public function test_contact_form_protection_needs_silentcaptcha_on() :void {
		$this->installController( [], [ 'opts_lookup' => $this->buildOptsLookupStub( [ 'enabledSilentCaptcha' => false ] ) ] );
		$row = $this->firstConfigureRow( new ContactFormSpamBlockBot() );

		$this->assertContains(
			"Contact forms aren't protected from bots because silentCAPTCHA is off: its minimum score is 0.",
			$row[ 'explanations' ]
		);
		// Configure shows a form integration that isn't working as a warning.
		$this->assertSame( EnumEnabledStatus::OKAY, $row[ 'enabled_status' ] );
	}

	public function test_file_scanning_says_plugin_and_theme_scans_need_a_temporary_folder() :void {
		$this->installController( [], [
			'scans' => $this->buildScansComponent( [ 'afsEnabled' => true ] ),
		], null, false );
		$row = $this->firstConfigureRow( new FileScanning() );

		$this->assertContains(
			"Plugin and theme files can't be scanned because Shield couldn't create a temporary folder.",
			$row[ 'explanations' ]
		);
	}

	public function test_file_locker_says_why_it_cannot_work_on_this_site() :void {
		$this->installController( [], [
			'file_locker' => new class {
				public function getFilesToLock() :array {
					return [ 'wpconfig', 'theme_functions', 'root_htaccess', 'root_index', 'root_webconfig' ];
				}

				public function lastCipherCheckFoundNone() :bool {
					return true;
				}
			},
			'license'     => new class {
				public function hasValidWorkingLicense() :bool {
					return true;
				}
			},
			'shieldnet'   => new class {
				public function canHandshake() :bool {
					return false;
				}
			},
		] );
		$row = $this->firstConfigureRow( $this->fileLockerForEnvironment() );

		$this->assertSame( EnumEnabledStatus::BAD, $row[ 'enabled_status' ] );
		$this->assertSame( [
			"File Locker can't work because the last check found no encryption cipher it can use. It needs the PHP OpenSSL extension and a cipher it supports.",
			"File Locker can't work because this site can't connect to shieldnet API.",
		], $row[ 'explanations' ] );
	}

	public function test_silentcaptcha_uses_warning_for_low_thresholds() :void {
		$this->installController(
			[ 'antibot_minimum' => 20 ],
			[
				'altcha' => new class {
					public function complexityLevel() :string {
						return 'low';
					}
				},
			]
		);

		$row = $this->firstConfigureRow( new SilentCaptcha() );

		$this->assertSame( EnumEnabledStatus::OKAY, $row[ 'enabled_status' ] ?? null );
		$this->assertCount( 2, $row[ 'explanations' ] ?? [] );
	}

	public function test_login_protection_requires_wordpress_login_form_for_critical() :void {
		$this->installController(
			[
				'bot_protection_locations' => [ 'register' ],
				'login_limit_interval'     => 0,
			],
			[
				'forms_users' => new class {
					public function getInstalled() :array {
						return [];
					}
				},
			]
		);

		$row = $this->firstConfigureRow( new LoginProtectionForms() );

		$this->assertSame( EnumEnabledStatus::BAD, $row[ 'enabled_status' ] ?? null );
		$this->assertCount( 3, $row[ 'explanations' ] ?? [] );
	}

	public function test_login_protection_uses_secondary_gaps_as_warning_once_login_is_protected() :void {
		$this->installController(
			[
				'bot_protection_locations' => [ 'login' ],
				'login_limit_interval'     => 0,
			],
			[
				'forms_users' => new class {
					public function getInstalled() :array {
						return [ 'third_party_form' => FakeThirdPartyLoginForm::class ];
					}
				},
			]
		);

		$row = $this->firstConfigureRow( new LoginProtectionForms() );

		$this->assertSame( EnumEnabledStatus::OKAY, $row[ 'enabled_status' ] ?? null );
		$this->assertCount( 4, $row[ 'explanations' ] ?? [] );
	}

	public function test_two_factor_general_row_uses_configure_status_path() :void {
		$this->installController( [
			'allow_backupcodes' => 'Y',
		] );

		$row = $this->findConfigureRowByKey( new TwoFactorAuth(), 'two_factor_general' );

		$this->assertSame( EnumEnabledStatus::BAD, $row[ 'enabled_status' ] ?? null );
		$this->assertCount( 1, $row[ 'explanations' ] ?? [] );
	}

	public function test_two_factor_email_row_requires_end_to_end_usability() :void {
		$this->installController( [
			'enable_email_authentication' => 'Y',
			'email_can_send_verified_at'  => 12345,
			'email_any_user_set'          => 'Y',
		] );

		$emailRow = $this->findConfigureRowByKey( new TwoFactorAuth(), 'two_factor_email' );
		$generalRow = $this->findConfigureRowByKey( new TwoFactorAuth(), 'two_factor_general' );
		$otpRow = $this->findConfigureRowByKey( new TwoFactorAuth(), 'two_factor_otp_passkeys' );

		$this->assertSame( EnumEnabledStatus::GOOD, $emailRow[ 'enabled_status' ] ?? null );
		$this->assertSame( EnumEnabledStatus::OKAY, $generalRow[ 'enabled_status' ] ?? null );
		$this->assertSame( EnumEnabledStatus::OKAY, $otpRow[ 'enabled_status' ] ?? null );
	}

	public function test_two_factor_email_row_warns_when_email_delivery_is_unverified() :void {
		$this->installController( [
			'enable_email_authentication' => 'Y',
			'email_can_send_verified_at'  => 0,
			'email_any_user_set'          => 'Y',
		] );

		$row = $this->findConfigureRowByKey( new TwoFactorAuth(), 'two_factor_email' );

		$this->assertSame( EnumEnabledStatus::OKAY, $row[ 'enabled_status' ] ?? null );
		$this->assertCount( 1, $row[ 'explanations' ] ?? [] );
	}

	public function test_two_factor_otp_and_passkeys_row_is_good_when_an_otp_provider_is_usable() :void {
		$this->installController( [
			'enable_google_authenticator' => 'Y',
		] );

		$row = $this->findConfigureRowByKey( new TwoFactorAuth(), 'two_factor_otp_passkeys' );

		$this->assertSame( EnumEnabledStatus::GOOD, $row[ 'enabled_status' ] ?? null );
		$this->assertSame( [], $row[ 'explanations' ] ?? [] );
	}

	public function test_login_guard_configure_rows_expose_stable_contract_scopes() :void {
		$this->installController( [
			'bot_protection_locations'    => [ 'login', 'register', 'password' ],
			'login_limit_interval'        => 30,
			'rename_wplogin_path'         => 'shield-login',
			'allow_backupcodes'           => 'Y',
			'enable_email_authentication' => 'Y',
			'email_can_send_verified_at'  => 12345,
			'email_any_user_set'          => 'Y',
			'enable_google_authenticator' => 'Y',
			'enable_passkeys'             => 'N',
		] );

		$this->assertConfigureRowContract(
			$this->findConfigureRowByKey( new LoginProtectionForms(), 'login_protection_forms' ),
			'login_protection_forms',
			EnumEnabledStatus::GOOD,
			[ 'login_protection_forms' ],
			[ 'bot_protection_locations', 'login_limit_interval' ]
		);
		$this->assertConfigureRowContract(
			$this->findConfigureRowByKey( new LoginHide(), 'login_hide' ),
			'login_hide',
			EnumEnabledStatus::NEUTRAL,
			[ 'login_hide' ],
			[ 'rename_wplogin_path', 'rename_wplogin_redirect' ]
		);
		$this->assertConfigureRowContract(
			$this->findConfigureRowByKey( new TwoFactorAuth(), 'two_factor_general' ),
			'two_factor_general',
			EnumEnabledStatus::GOOD,
			[ 'two_factor_auth' ],
			[ 'mfa_verify_page', 'allow_backupcodes' ]
		);
		$this->assertConfigureRowContract(
			$this->findConfigureRowByKey( new TwoFactorAuth(), 'two_factor_email' ),
			'two_factor_email',
			EnumEnabledStatus::GOOD,
			[ 'two_factor_auth' ],
			[ 'enable_email_authentication', 'email_can_send_verified_at', 'two_factor_auth_user_roles' ]
		);
		$this->assertConfigureRowContract(
			$this->findConfigureRowByKey( new TwoFactorAuth(), 'two_factor_otp_passkeys' ),
			'two_factor_otp_passkeys',
			EnumEnabledStatus::GOOD,
			[ 'two_factor_auth' ],
			[ 'enable_google_authenticator', 'enable_passkeys' ]
		);
	}

	public function test_users_configure_rows_expose_stable_contract_scopes() :void {
		$this->installController(
			[
				'enable_password_policies' => 'Y',
				'pass_prevent_pwned'       => 'Y',
				'pass_min_strength'        => 3,
				'manual_suspend'           => 'Y',
				'auto_password'            => 'Y',
				'auto_idle_days'           => 14,
			],
			[
				'opts_lookup'  => $this->buildOptsLookupStub( [
					'isPassPoliciesEnabled' => true,
					'getEmailValidateChecks' => [ 'syntax' ],
					'getPassExpireTimeout'  => 90,
				] ),
				'user_suspend' => new class {
					public function isSuspendManualEnabled() :bool {
						return true;
					}

					public function isSuspendAutoIdleEnabled() :bool {
						return true;
					}

					public function isSuspendAutoPasswordEnabled() :bool {
						return true;
					}
				},
			]
		);

		$this->assertConfigureRowContract(
			$this->findConfigureRowByKey( new PasswordPolicies(), 'password_policies' ),
			'password_policies',
			EnumEnabledStatus::GOOD,
			[ 'password_policies' ],
			[ 'enable_password_policies' ]
		);
		$this->assertConfigureRowContract(
			$this->findConfigureRowByKey( new PwnedPasswords(), 'pwned_passwords' ),
			'pwned_passwords',
			EnumEnabledStatus::GOOD,
			[ 'pwned_passwords' ],
			[ 'enable_password_policies', 'pass_prevent_pwned' ]
		);
		$this->assertConfigureRowContract(
			$this->findConfigureRowByKey( new PasswordStrength(), 'password_strength' ),
			'password_strength',
			EnumEnabledStatus::GOOD,
			[ 'password_strength' ],
			[ 'enable_password_policies', 'pass_min_strength' ]
		);
		$this->assertConfigureRowContract(
			$this->findConfigureRowByKey( new InactiveUsers(), 'inactive_users' ),
			'inactive_users',
			EnumEnabledStatus::GOOD,
			[ 'inactive_users' ],
			[ 'manual_suspend', 'auto_password', 'auto_idle_days' ]
		);
		$this->assertConfigureRowContract(
			$this->findConfigureRowByKey( new SpamUserRegisterBlock(), 'spam_user_register_block' ),
			'spam_user_register_block',
			EnumEnabledStatus::GOOD,
			[ 'spam_user_register_block' ],
			[ 'reg_email_validate', 'email_checks' ]
		);
	}

	public function test_inactive_users_row_warns_when_only_manual_suspension_is_available() :void {
		$this->installController(
			[],
			[
				'user_suspend' => new class {
					public function isSuspendManualEnabled() :bool {
						return true;
					}

					public function isSuspendAutoIdleEnabled() :bool {
						return false;
					}

					public function isSuspendAutoPasswordEnabled() :bool {
						return false;
					}
				},
			]
		);

		$row = $this->firstConfigureRow( new InactiveUsers() );

		$this->assertSame( EnumEnabledStatus::OKAY, $row[ 'enabled_status' ] ?? null );
		$this->assertCount( 1, $row[ 'explanations' ] ?? [] );
	}

	public function test_inactive_users_row_explains_ineffective_password_expiry_suspension() :void {
		$this->installController(
			[
				'auto_password' => 'Y',
			],
			[
				'opts_lookup' => $this->buildOptsLookupStub( [
					'isPassPoliciesEnabled' => false,
					'getPassExpireTimeout'  => 0,
				] ),
				'user_suspend' => new class {
					public function isSuspendManualEnabled() :bool {
						return false;
					}

					public function isSuspendAutoIdleEnabled() :bool {
						return false;
					}

					public function isSuspendAutoPasswordEnabled() :bool {
						return false;
					}
				},
			]
		);

		$row = $this->firstConfigureRow( new InactiveUsers() );

		$this->assertSame( EnumEnabledStatus::OKAY, $row[ 'enabled_status' ] ?? null );
		$this->assertCount( 3, $row[ 'explanations' ] ?? [] );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function firstConfigureRow( ComponentBase $component ) :array {
		$rows = $component->configureRows();
		$this->assertNotEmpty( $rows );
		return $rows[ 0 ];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function findConfigureRowByKey( ComponentBase $component, string $rowKey ) :array {
		foreach ( $component->configureRows() as $row ) {
			if ( ( $row[ 'key' ] ?? '' ) === $rowKey ) {
				return $row;
			}
		}

		$this->fail( 'Failed to locate configure row: '.$rowKey );
	}

	private function assertConfigureRowContract(
		array $row,
		string $key,
		string $enabledStatus,
		array $zoneComponentSlugs,
		array $optionKeys
	) :void {
		$this->assertSame( $key, $row[ 'key' ] ?? null );
		$this->assertSame( $enabledStatus, $row[ 'enabled_status' ] ?? null );
		$this->assertSame( $zoneComponentSlugs, $row[ 'config_scope' ][ 'zone_component_slugs' ] ?? null );
		foreach ( $optionKeys as $optionKey ) {
			$this->assertContains( $optionKey, $row[ 'config_scope' ][ 'option_keys' ] ?? [] );
		}
	}

	private function installController(
		array $optValues = [],
		array $componentOverrides = [],
		?array $configOptions = null,
		bool $cacheDirExists = true
	) :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
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
		$controller->caps = new class {
			public function __call( string $name, array $arguments ) :bool {
				return false;
			}
		};
		$controller->cfg = (object)[
			'configuration' => (object)[
				'options'  => $configOptions ?? $this->minimalConfigOptions(),
				'sections' => [],
			],
		];
		$controller->opts = new class( $optValues ) {
			private array $values;

			public function __construct( array $values ) {
				$this->values = $values;
			}

			public function optGet( string $key ) {
				return $this->values[ $key ] ?? null;
			}

			public function optIs( string $key, $value ) :bool {
				return $this->optGet( $key ) == $value;
			}
		};
		$controller->comps = (object)\array_merge(
			[
				'scans'       => $this->buildScansComponent(),
				'file_locker' => new class {
					public function getFilesToLock() :array {
						return [];
					}

					public function lastCipherCheckFoundNone() :bool {
						return false;
					}
				},
				'altcha'      => new class {
					public function complexityLevel() :string {
						return 'high';
					}
				},
				'bot_signals' => new class {
					public function getAllowableExt404s() :array {
						return [ 'jpg' ];
					}

					public function getAllowableScripts() :array {
						return [ 'index.php' ];
					}
				},
				'forms_users' => new class {
					public function getInstalled() :array {
						return [];
					}
				},
				'forms_spam'  => new class {
					public function getInstalled() :array {
						return [];
					}
				},
				'opts_lookup' => $this->buildOptsLookupStub(),
				'user_suspend' => new class {
					public function isSuspendManualEnabled() :bool {
						return false;
					}

					public function isSuspendAutoIdleEnabled() :bool {
						return true;
					}

					public function isSuspendAutoPasswordEnabled() :bool {
						return false;
					}
				},
				'sec_admin' => new class {
					public function isEnabledSecAdmin() :bool {
						return true;
					}
				},
				'mfa' => new class {
					public function collateMfaProviderClasses() :array {
						return [ FakeEnabledMfaProvider::class, FakeEnabledMfaProvider::class ];
					}
				},
				'zones' => new SecurityZonesCon(),
				'license' => new class {
					public function hasValidWorkingLicense() :bool {
						return false;
					}
				},
				'shieldnet' => new class {
					public function canHandshake() :bool {
						return true;
					}
				},
			],
			$componentOverrides
		);
		$controller->cache_dir_handler = new class( $cacheDirExists ) {
			private bool $exists;

			public function __construct( bool $exists ) {
				$this->exists = $exists;
			}

			public function exists() :bool {
				return $this->exists;
			}
		};

		PluginControllerInstaller::install( $controller );
	}

	private function buildOptsLookupStub( array $overrides = [] ) :object {
		return new class( $overrides ) {
			private array $overrides;

			public function __construct( array $overrides ) {
				$this->overrides = $overrides;
			}

			public function isPassPoliciesEnabled() :bool {
				return $this->overrides[ 'isPassPoliciesEnabled' ] ?? true;
			}

			public function isPluginEnabled() :bool {
				return $this->overrides[ 'isPluginEnabled' ] ?? true;
			}

			public function enabledTrafficLimiter() :bool {
				return $this->overrides[ 'enabledTrafficLimiter' ] ?? true;
			}

			public function enabledCrowdSecAutoBlock() :bool {
				return $this->overrides[ 'enabledCrowdSecAutoBlock' ] ?? true;
			}

			public function getEmailValidateChecks() :array {
				return $this->overrides[ 'getEmailValidateChecks' ] ?? [ 'mx' ];
			}

			public function enabledSilentCaptchaCommentSpam() :bool {
				return $this->overrides[ 'enabledSilentCaptchaCommentSpam' ] ?? true;
			}

			public function enabledSilentCaptcha() :bool {
				return $this->overrides[ 'enabledSilentCaptcha' ] ?? true;
			}

			public function enabledIpAutoBlock() :bool {
				return $this->overrides[ 'enabledIpAutoBlock' ] ?? true;
			}

			public function enabledHumanCommentSpam() :bool {
				return $this->overrides[ 'enabledHumanCommentSpam' ] ?? true;
			}

			public function getCommenterTrustedMinimum() :int {
				return $this->overrides[ 'getCommenterTrustedMinimum' ] ?? 2;
			}

			public function getLoginGuardEmailAuth2FaRoles() :array {
				return $this->overrides[ 'getLoginGuardEmailAuth2FaRoles' ] ?? [];
			}

			public function getPassExpireTimeout() :int {
				return $this->overrides[ 'getPassExpireTimeout' ] ?? 0;
			}

			public function getSessionIdleInterval() :int {
				return $this->overrides[ 'getSessionIdleInterval' ] ?? 0;
			}
		};
	}

	private function fileLockerForEnvironment(
		bool $isWindows = true,
		bool $themeFunctionsAccessible = true
	) :FileLocker {
		return new FileLocker(
			new FileLockKeyApplicability( $isWindows, $themeFunctionsAccessible )
		);
	}

	private function minimalConfigOptions() :array {
		$options = [];
		foreach ( [
			'clean_wp_rubbish' => [ 'module_firewall', 'section_wordpress_obscurity_options' ],
			'bot_protection_locations' => [ 'login_protection_forms', '' ],
			'login_limit_interval' => [ 'login_protection_forms', '' ],
			'rename_wplogin_path' => [ 'login_hide,module_login', '' ],
			'rename_wplogin_redirect' => [ 'login_hide,module_login', '' ],
			'mfa_verify_page' => [ 'two_factor_auth,module_login', 'section_twofactor_auth' ],
			'allow_backupcodes' => [ 'two_factor_auth,module_login', 'section_twofactor_auth' ],
			'enable_email_authentication' => [ 'two_factor_auth,module_login', 'section_2fa_email' ],
			'email_can_send_verified_at' => [ 'two_factor_auth,module_login', 'section_2fa_email' ],
			'two_factor_auth_user_roles' => [ 'two_factor_auth,module_login', 'section_2fa_email' ],
			'enable_google_authenticator' => [ 'two_factor_auth,module_login', 'section_2fa_otp' ],
			'enable_passkeys' => [ 'two_factor_auth,module_login', 'section_2fa_passkeys' ],
			'enable_password_policies' => [ 'password_policies,password_strength,pwned_passwords,module_users', 'section_passwords' ],
			'pass_prevent_pwned' => [ 'pwned_passwords,module_users', 'section_passwords' ],
			'pass_min_strength' => [ 'password_strength,module_users', 'section_passwords' ],
			'pass_expire' => [ 'password_policies,module_users', 'section_passwords' ],
			'pass_force_existing' => [ 'password_policies,module_users', 'section_passwords' ],
			'manual_suspend' => [ 'inactive_users,module_users', 'section_suspend' ],
			'auto_password' => [ 'inactive_users,module_users', 'section_suspend' ],
			'auto_idle_days' => [ 'inactive_users,module_users', 'section_suspend' ],
			'reg_email_validate' => [ 'spam_user_register_block,module_users', 'section_user_reg' ],
			'email_checks' => [ 'spam_user_register_block,module_users', 'section_user_reg' ],
		] as $key => [ $slug, $section ] ) {
			$options[ $key ] = [
				'section'         => $section,
				'zone_comp_slugs' => \explode( ',', $slug ),
			];
		}
		return $options;
	}

	private function buildScansComponent( array $overrides = [] ) :object {
		$settings = \array_merge(
			[
				'afsEnabled'             => false,
				'fileScanAreas'          => [],
				'malwareEnabled'         => false,
				'wpCoreEnabled'          => false,
				'pluginsEnabled'         => false,
				'themesEnabled'          => false,
				'wpContentEnabled'       => false,
				'wpRootEnabled'          => false,
				'repairWp'               => false,
				'repairPlugin'           => false,
				'repairTheme'            => false,
				'wpvEnabled'             => false,
				'wpvAutoupdatesEnabled'  => false,
				'apcEnabled'             => false,
			],
			$overrides
		);

		return new class( $settings ) {
			private array $settings;

			public function __construct( array $settings ) {
				$this->settings = $settings;
			}

			public function AFS() :object {
				return new class( $this->settings ) {
					private array $settings;

					public function __construct( array $settings ) {
						$this->settings = $settings;
					}

					public function isEnabled() :bool {
						return $this->settings[ 'afsEnabled' ];
					}

					public function getFileScanAreas() :array {
						return $this->settings[ 'fileScanAreas' ];
					}

					public function isEnabledMalwareScanPHP() :bool {
						return $this->settings[ 'malwareEnabled' ];
					}

					public function isScanEnabledWpCore() :bool {
						return $this->settings[ 'wpCoreEnabled' ];
					}

					public function isScanEnabledPlugins() :bool {
						return $this->settings[ 'pluginsEnabled' ];
					}

					public function isScanEnabledThemes() :bool {
						return $this->settings[ 'themesEnabled' ];
					}

					public function isScanEnabledWpContent() :bool {
						return $this->settings[ 'wpContentEnabled' ];
					}

					public function isScanEnabledWpRoot() :bool {
						return $this->settings[ 'wpRootEnabled' ];
					}

					public function isRepairFileWP() :bool {
						return $this->settings[ 'repairWp' ];
					}

					public function isRepairFilePlugin() :bool {
						return $this->settings[ 'repairPlugin' ];
					}

					public function isRepairFileTheme() :bool {
						return $this->settings[ 'repairTheme' ];
					}
				};
			}

			public function WPV() :object {
				return new class( $this->settings ) {
					private array $settings;

					public function __construct( array $settings ) {
						$this->settings = $settings;
					}

					public function isEnabled() :bool {
						return $this->settings[ 'wpvEnabled' ];
					}

					public function isAutoupdatesEnabled() :bool {
						return $this->settings[ 'wpvAutoupdatesEnabled' ];
					}
				};
			}

			public function APC() :object {
				return new class( $this->settings ) {
					private array $settings;

					public function __construct( array $settings ) {
						$this->settings = $settings;
					}

					public function isEnabled() :bool {
						return $this->settings[ 'apcEnabled' ];
					}
				};
			}
		};
	}
}

class FakeThirdPartyLoginForm {

	public function isEnabled() :bool {
		return false;
	}

	public function getHandlerName() :string {
		return 'Third-party Login Form';
	}
}

class FakeSpamForm {

	public function isEnabled() :bool {
		return false;
	}

	public function getHandlerName() :string {
		return 'Fake Spam Form';
	}
}

class FakeEnabledMfaProvider {

	public static function ProviderEnabled() :bool {
		return true;
	}
}
