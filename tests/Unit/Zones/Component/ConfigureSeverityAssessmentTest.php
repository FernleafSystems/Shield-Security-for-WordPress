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
	LoginProtectionForms,
	PasswordPolicies,
	PasswordStrength,
	PwnedPasswords,
	RateLimiting,
	ScanScheduling,
	SecadminEnabled,
	SecadminWpAdmins,
	SecadminWpOptions,
	SilentCaptcha,
	SpamUserRegisterBlock,
	TrustedCommenters,
	TwoFactorAuth,
	UsernameFishingBlock,
	VulnerabilityScanning,
	WebApplicationFirewall,
	XmlRpcDisable
};

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
			'inactive users'      => [ fn() => new InactiveUsers(), [], [ 'user_suspend' => new class { public function isSuspendAutoIdleEnabled() :bool { return false; } } ] ],
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
		$signals = $this->indexSignalsBySlug( ( new FileScanning() )->postureSignals() );

		$this->assertSame( EnumEnabledStatus::OKAY, $row[ 'enabled_status' ] ?? null );
		$this->assertCount( 2, $row[ 'explanations' ] ?? [] );
		$this->assertSame( 'critical', $signals[ 'scan_enabled_afs_core' ][ 'severity' ] ?? null );
		$this->assertSame( 'warning', $signals[ 'scan_enabled_afs_autorepair_core' ][ 'severity' ] ?? null );
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
		$signals = $this->indexSignalsBySlug( ( new VulnerabilityScanning() )->postureSignals() );

		$this->assertSame( EnumEnabledStatus::OKAY, $row[ 'enabled_status' ] ?? null );
		$this->assertCount( 2, $row[ 'explanations' ] ?? [] );
		$this->assertSame( 'warning', $signals[ 'scan_enabled_apc' ][ 'severity' ] ?? null );
		$this->assertSame( 'warning', $signals[ 'scan_enabled_wpv_autoupdate' ][ 'severity' ] ?? null );
	}

	public function test_file_locker_requires_wpconfig_and_accepts_real_lock_keys() :void {
		$this->installController(
			[],
			[
				'file_locker' => new class {
					public function getFilesToLock() :array {
						return [ 'theme_functions', 'root_htaccess', 'root_index', 'root_webconfig' ];
					}
				},
			]
		);

		$row = $this->firstConfigureRow( new FileLocker() );

		$this->assertSame( EnumEnabledStatus::BAD, $row[ 'enabled_status' ] ?? null );
		$this->assertCount( 1, $row[ 'explanations' ] ?? [] );

		$this->installController(
			[],
			[
				'file_locker' => new class {
					public function getFilesToLock() :array {
						return [ 'wpconfig', 'theme_functions', 'root_htaccess', 'root_index', 'root_webconfig' ];
					}
				},
			]
		);

		$signals = ( new FileLocker() )->postureSignals();

		$this->assertSame(
			[ 'good', 'good', 'good', 'good', 'good' ],
			\array_column( $signals, 'severity' )
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

	public function test_bot_actions_use_partial_primary_coverage_and_shared_signal_list() :void {
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
		$this->assertContains( 'bot_signal_track_linkcheese', \array_column( $component->postureSignals(), 'slug' ) );
		$this->assertContains( 'bot_signal_track_useragent', \array_column( $component->postureSignals(), 'slug' ) );
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
		$this->installController(
			[],
			[
				'mfa' => new class {
					public function collateMfaProviderClasses() :array {
						return [];
					}
				},
			]
		);

		$row = $this->findConfigureRowByKey( new TwoFactorAuth(), 'two_factor_general' );

		$this->assertSame( EnumEnabledStatus::BAD, $row[ 'enabled_status' ] ?? null );
		$this->assertCount( 1, $row[ 'explanations' ] ?? [] );
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

	/**
	 * @param array<int,array<string,mixed>> $signals
	 * @return array<string,array<string,mixed>>
	 */
	private function indexSignalsBySlug( array $signals ) :array {
		$indexed = [];
		foreach ( $signals as $signal ) {
			$slug = $signal[ 'slug' ] ?? null;
			if ( \is_string( $slug ) && $slug !== '' ) {
				$indexed[ $slug ] = $signal;
			}
		}
		return $indexed;
	}

	private function installController( array $optValues = [], array $componentOverrides = [] ) :void {
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
				'options'  => [],
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
					public function isSuspendAutoIdleEnabled() :bool {
						return true;
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
			],
			$componentOverrides
		);

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

			public function enabledHumanCommentSpam() :bool {
				return $this->overrides[ 'enabledHumanCommentSpam' ] ?? true;
			}

			public function getCommenterTrustedMinimum() :int {
				return $this->overrides[ 'getCommenterTrustedMinimum' ] ?? 2;
			}
		};
	}

	private function buildScansComponent( array $overrides = [] ) :object {
		$settings = \array_merge(
			[
				'afsEnabled'             => false,
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
