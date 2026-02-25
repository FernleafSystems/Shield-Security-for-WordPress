<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageInvestigateLanding;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\{
	Plugins,
	Request,
	Themes
};
use FernleafSystems\Wordpress\Services\Utilities\IpUtils;

class PageInvestigateLandingBehaviorTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( 'sanitize_text_field' )->alias(
			fn( $text ) => $text
		);
		Functions\when( '__' )->alias(
			fn( string $text ) :string => $text
		);
		Functions\when( 'sanitize_key' )->alias(
			fn( $text ) => \is_string( $text ) ? \strtolower( \trim( $text ) ) : ''
		);
		$this->servicesSnapshot = ServicesState::snapshot();
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_active_subject_prefers_explicit_subject_query_parameter() :void {
		$this->installServicesStubs( [
			'subject'     => 'themes',
			PluginNavs::FIELD_SUBNAV => PluginNavs::SUBNAV_ACTIVITY_BY_IP,
			'plugin_slug' => 'akismet/akismet.php',
		] );
		$page = new PageInvestigateLanding();

		$vars = $this->invokeProtectedMethod( $page, 'getLandingVars' );
		$this->assertSame( 'themes', $vars[ 'active_subject' ] );
	}

	public function test_active_subject_uses_subnav_hint_when_subject_query_is_absent() :void {
		$this->installServicesStubs( [
			PluginNavs::FIELD_SUBNAV => PluginNavs::SUBNAV_ACTIVITY_BY_CORE,
			'user_lookup'            => 'admin',
		] );
		$page = new PageInvestigateLanding();

		$vars = $this->invokeProtectedMethod( $page, 'getLandingVars' );
		$this->assertSame( 'wordpress', $vars[ 'active_subject' ] );
	}

	public function test_active_subject_uses_input_precedence_and_falls_back_to_users() :void {
		$this->installServicesStubs( [
			'plugin_slug' => 'security/plugin.php',
			'theme_slug'  => 'twentytwentyfour',
			'analyse_ip'  => '1.2.3.4',
			'user_lookup' => 'admin',
		] );
		$page = new PageInvestigateLanding();
		$this->assertSame( 'plugins', $this->invokeProtectedMethod( $page, 'getLandingVars' )[ 'active_subject' ] );

		$this->installServicesStubs( [
			'theme_slug'  => 'twentytwentyfour',
			'analyse_ip'  => '1.2.3.4',
			'user_lookup' => 'admin',
		] );
		$page = new PageInvestigateLanding();
		$this->assertSame( 'themes', $this->invokeProtectedMethod( $page, 'getLandingVars' )[ 'active_subject' ] );

		$this->installServicesStubs( [
			'analyse_ip'  => '1.2.3.4',
			'user_lookup' => 'admin',
		] );
		$page = new PageInvestigateLanding();
		$this->assertSame( 'ips', $this->invokeProtectedMethod( $page, 'getLandingVars' )[ 'active_subject' ] );

		$this->installServicesStubs( [
			'user_lookup' => 'admin',
		] );
		$page = new PageInvestigateLanding();
		$this->assertSame( 'users', $this->invokeProtectedMethod( $page, 'getLandingVars' )[ 'active_subject' ] );

		$this->installServicesStubs();
		$page = new PageInvestigateLanding();
		$this->assertSame( 'users', $this->invokeProtectedMethod( $page, 'getLandingVars' )[ 'active_subject' ] );
	}

	public function test_input_values_are_persisted_in_landing_vars() :void {
		$this->installServicesStubs( [
			'user_lookup' => '  admin@example.com ',
			'analyse_ip'  => ' 2001:db8::1 ',
			'plugin_slug' => ' my-plugin/main.php ',
			'theme_slug'  => ' twentytwentyfour ',
		] );
		$page = new PageInvestigateLanding();

		$input = $this->invokeProtectedMethod( $page, 'getLandingVars' )[ 'input' ];
		$this->assertSame( 'admin@example.com', $input[ 'user_lookup' ] );
		$this->assertSame( '2001:db8::1', $input[ 'analyse_ip' ] );
		$this->assertSame( 'my-plugin/main.php', $input[ 'plugin_slug' ] );
		$this->assertSame( 'twentytwentyfour', $input[ 'theme_slug' ] );
	}

	public function test_landing_vars_include_loop_ready_subject_contract() :void {
		$this->installServicesStubs(
			[
				'subject'     => 'plugins',
				'plugin_slug' => 'akismet/akismet.php',
				'theme_slug'  => 'astra',
			],
			[
				(object)[
					'file'    => 'akismet/akismet.php',
					'Name'    => 'Akismet',
					'Version' => '5.0',
				],
			],
			[
				(object)[
					'stylesheet' => 'astra',
					'Name'       => 'Astra',
					'Version'    => '4.6',
				],
			]
		);
		$page = new PageInvestigateLanding();

		$vars = $this->invokeProtectedMethod( $page, 'getLandingVars' );
		$this->assertCount( 7, $vars[ 'subjects' ] );
		$this->assertSame(
			[ 'users', 'ips', 'plugins', 'themes', 'wordpress', 'requests', 'activity' ],
			\array_column( $vars[ 'subjects' ], 'key' )
		);

		$plugins = \array_values(
			\array_filter(
				$vars[ 'subjects' ],
				static fn( array $subject ) :bool => $subject[ 'key' ] === 'plugins'
			)
		)[ 0 ];
		$themes = \array_values(
			\array_filter(
				$vars[ 'subjects' ],
				static fn( array $subject ) :bool => $subject[ 'key' ] === 'themes'
			)
		)[ 0 ];

		$optionsKeysBySubject = [];
		foreach ( $vars[ 'subjects' ] as $subject ) {
			$optionsKeysBySubject[ $subject[ 'key' ] ] = $subject[ 'options_key' ];
		}
		$this->assertSame(
			[
				'users'     => null,
				'ips'       => null,
				'plugins'   => 'plugin_options',
				'themes'    => 'theme_options',
				'wordpress' => null,
				'requests'  => null,
				'activity'  => null,
			],
			$optionsKeysBySubject
		);

		$this->assertTrue( $plugins[ 'is_active' ] );
		$this->assertSame( 'lookup_select', $plugins[ 'panel_type' ] );
		$this->assertSame( '/admin/activity/by_plugin', $plugins[ 'href' ] );
		$this->assertSame( 'plugin_slug', $plugins[ 'input_key' ] );
		$this->assertSame( 'akismet/akismet.php', $plugins[ 'input_value' ] );
		$this->assertSame( 'plugin_options', $plugins[ 'options_key' ] );
		$this->assertSame( $vars[ 'plugin_options' ], $plugins[ 'options' ] );
		$this->assertSame( $vars[ 'theme_options' ], $themes[ 'options' ] );
		$this->assertNotEmpty( $plugins[ 'subject_label' ] );
		$this->assertNotEmpty( $plugins[ 'panel_title' ] );
		$this->assertNotEmpty( $plugins[ 'lookup_placeholder' ] );
		$this->assertNotEmpty( $plugins[ 'go_label' ] );

		$subjectsByKey = [];
		foreach ( $vars[ 'subjects' ] as $subject ) {
			$subjectsByKey[ $subject[ 'key' ] ] = $subject;
		}
		$this->assertSame(
			[
				'page'    => 'icwp-wpsf-plugin',
				'nav'     => PluginNavs::NAV_ACTIVITY,
				'nav_sub' => PluginNavs::SUBNAV_ACTIVITY_BY_USER,
			],
			$subjectsByKey[ 'users' ][ 'lookup_route' ] ?? []
		);
		$this->assertSame(
			[
				'page'    => 'icwp-wpsf-plugin',
				'nav'     => PluginNavs::NAV_ACTIVITY,
				'nav_sub' => PluginNavs::SUBNAV_ACTIVITY_BY_IP,
			],
			$subjectsByKey[ 'ips' ][ 'lookup_route' ] ?? []
		);
		$this->assertSame(
			[
				'page'    => 'icwp-wpsf-plugin',
				'nav'     => PluginNavs::NAV_ACTIVITY,
				'nav_sub' => PluginNavs::SUBNAV_ACTIVITY_BY_PLUGIN,
			],
			$subjectsByKey[ 'plugins' ][ 'lookup_route' ] ?? []
		);
		$this->assertSame(
			[
				'page'    => 'icwp-wpsf-plugin',
				'nav'     => PluginNavs::NAV_ACTIVITY,
				'nav_sub' => PluginNavs::SUBNAV_ACTIVITY_BY_THEME,
			],
			$subjectsByKey[ 'themes' ][ 'lookup_route' ] ?? []
		);
		$this->assertSame( [], $subjectsByKey[ 'wordpress' ][ 'lookup_route' ] ?? [ 'unexpected' ] );
		$this->assertSame( [], $subjectsByKey[ 'requests' ][ 'lookup_route' ] ?? [ 'unexpected' ] );
		$this->assertSame( [], $subjectsByKey[ 'activity' ][ 'lookup_route' ] ?? [ 'unexpected' ] );
	}

	public function test_request_input_values_are_cached_across_repeated_internal_access() :void {
		$queryCallCount = (object)[
			'count' => 0,
		];
		$this->installServicesStubs(
			[
				'user_lookup' => 'admin',
				'analyse_ip'  => '203.0.113.9',
				'plugin_slug' => 'akismet/akismet.php',
				'theme_slug'  => 'astra',
			],
			[],
			[],
			null,
			$queryCallCount
		);
		$page = new PageInvestigateLanding();

		$this->invokeProtectedMethod( $page, 'getLandingVars' );
		$countAfterLandingVars = $queryCallCount->count;
		$this->assertGreaterThan( 0, $countAfterLandingVars );

		$this->invokeProtectedMethod( $page, 'getLandingFlags' );
		$this->assertSame( $countAfterLandingVars, $queryCallCount->count );
	}

	public function test_ip_lookup_flags_use_has_lookup_and_validity_contract() :void {
		$this->installServicesStubs(
			[ 'analyse_ip' => 'not-an-ip' ],
			[],
			[],
			fn( string $ip ) :bool => \filter_var( $ip, \FILTER_VALIDATE_IP ) !== false
		);
		$page = new PageInvestigateLanding();

		$flags = $this->invokeProtectedMethod( $page, 'getLandingFlags' );
		$this->assertTrue( $flags[ 'has_ip_lookup' ] );
		$this->assertFalse( $flags[ 'ip_is_valid' ] );

		$this->installServicesStubs(
			[ 'analyse_ip' => '203.0.113.22' ],
			[],
			[],
			fn( string $ip ) :bool => \filter_var( $ip, \FILTER_VALIDATE_IP ) !== false
		);
		$page = new PageInvestigateLanding();

		$flags = $this->invokeProtectedMethod( $page, 'getLandingFlags' );
		$this->assertTrue( $flags[ 'has_ip_lookup' ] );
		$this->assertTrue( $flags[ 'ip_is_valid' ] );
	}

	public function test_plugin_and_theme_options_map_to_value_label_lists_using_expected_identifiers() :void {
		$this->installServicesStubs(
			[],
			[
				(object)[
					'file'    => 'zeta/zeta.php',
					'Name'    => 'Zeta',
					'Version' => '1.0.0',
				],
				(object)[
					'file'    => 'alpha/alpha.php',
					'Name'    => 'Alpha',
					'Version' => '2.5.0',
				],
			],
			[
				(object)[
					'stylesheet' => 'twentytwentythree',
					'Name'       => 'Twenty Twenty-Three',
					'Version'    => '1.9',
				],
				(object)[
					'stylesheet' => 'astra',
					'Name'       => 'Astra',
					'Version'    => '4.6',
				],
			]
		);
		$page = new PageInvestigateLanding();
		$vars = $this->invokeProtectedMethod( $page, 'getLandingVars' );

		$this->assertSame(
			[
				[
					'value' => 'alpha/alpha.php',
					'label' => 'Alpha (2.5.0)',
				],
				[
					'value' => 'zeta/zeta.php',
					'label' => 'Zeta (1.0.0)',
				],
			],
			$vars[ 'plugin_options' ]
		);
		$this->assertSame(
			[
				[
					'value' => 'astra',
					'label' => 'Astra (4.6)',
				],
				[
					'value' => 'twentytwentythree',
					'label' => 'Twenty Twenty-Three (1.9)',
				],
			],
			$vars[ 'theme_options' ]
		);
	}

	public function test_option_lists_are_memoized_across_subject_and_top_level_access() :void {
		$optionsBuildCount = (object)[
			'plugins' => 0,
			'themes'  => 0,
		];
		$this->installServicesStubs(
			[],
			[
				(object)[
					'file'    => 'akismet/akismet.php',
					'Name'    => 'Akismet',
					'Version' => '5.0',
				],
			],
			[
				(object)[
					'stylesheet' => 'astra',
					'Name'       => 'Astra',
					'Version'    => '4.6',
				],
			],
			null,
			null,
			$optionsBuildCount
		);
		$page = new PageInvestigateLanding();

		$this->invokeProtectedMethod( $page, 'getLandingVars' );
		$this->assertSame( 1, $optionsBuildCount->plugins );
		$this->assertSame( 1, $optionsBuildCount->themes );
	}

	public function test_subject_definition_contract_violation_throws_logic_exception() :void {
		$this->installServicesStubs();
		$page = new class extends PageInvestigateLanding {
			protected function getSubjectDefinitions() :array {
				return [
					'plugins' => [
						'key'         => 'plugins',
						'subnav_hint' => PluginNavs::SUBNAV_ACTIVITY_BY_PLUGIN,
						'input_key'   => 'plugin_slug',
						'options_key' => null,
						'panel_type'  => 'lookup_select',
						'href_key'    => 'by_plugin',
						'string_keys' => [
							'subject' => 'subject_plugins',
							'panel'   => 'panel_plugins',
							'lookup'  => 'lookup_plugin',
							'go'      => 'go_plugin',
						],
					],
				];
			}
		};

		$this->expectException( \LogicException::class );
		$this->expectExceptionMessage( 'lookup_select requires options_key' );
		$this->invokeProtectedMethod( $page, 'getLandingVars' );
	}

	public function test_lookup_subject_without_subnav_hint_throws_logic_exception() :void {
		$this->installServicesStubs();
		$page = new class extends PageInvestigateLanding {
			protected function getSubjectDefinitions() :array {
				return [
					'users' => [
						'key'         => 'users',
						'subnav_hint' => '',
						'input_key'   => 'user_lookup',
						'options_key' => null,
						'panel_type'  => 'lookup_text',
						'href_key'    => 'by_user',
						'string_keys' => [
							'subject' => 'subject_users',
							'panel'   => 'panel_users',
							'lookup'  => 'lookup_user',
							'go'      => 'go_user',
						],
					],
				];
			}
		};

		$this->expectException( \LogicException::class );
		$this->expectExceptionMessage( 'lookup panel requires subnav_hint' );
		$this->invokeProtectedMethod( $page, 'getLandingVars' );
	}

	public function test_landing_hrefs_include_required_subject_and_tool_routes() :void {
		$this->installServicesStubs();
		$page = new PageInvestigateLanding();

		$hrefs = $this->invokeProtectedMethod( $page, 'getLandingHrefs' );
		$this->assertArrayHasKey( 'activity_log', $hrefs );
		$this->assertArrayHasKey( 'traffic_log', $hrefs );
		$this->assertArrayHasKey( 'live_traffic', $hrefs );
		$this->assertArrayHasKey( 'ip_rules', $hrefs );
		$this->assertArrayHasKey( 'by_user', $hrefs );
		$this->assertArrayHasKey( 'by_ip', $hrefs );
		$this->assertArrayHasKey( 'by_plugin', $hrefs );
		$this->assertArrayHasKey( 'by_theme', $hrefs );
		$this->assertArrayHasKey( 'by_core', $hrefs );

		$this->assertSame( '/admin/activity/by_user', $hrefs[ 'by_user' ] );
		$this->assertSame( '/admin/activity/by_ip', $hrefs[ 'by_ip' ] );
		$this->assertSame( '/admin/activity/by_plugin', $hrefs[ 'by_plugin' ] );
		$this->assertSame( '/admin/activity/by_theme', $hrefs[ 'by_theme' ] );
		$this->assertSame( '/admin/activity/by_core', $hrefs[ 'by_core' ] );
	}

	private function installControllerStub() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new class {
			public function rootAdminPageSlug() :string {
				return 'icwp-wpsf-plugin';
			}

			public function adminTopNav( string $nav, string $subnav = '' ) :string {
				return '/admin/'.$nav.'/'.$subnav;
			}

			public function investigateByIp( string $ip = '' ) :string {
				return empty( $ip ) ? '/admin/activity/by_ip' : '/admin/activity/by_ip?analyse_ip='.$ip;
			}

			public function investigateByUser( string $lookup = '' ) :string {
				return empty( $lookup ) ? '/admin/activity/by_user' : '/admin/activity/by_user?user_lookup='.$lookup;
			}

			public function investigateByPlugin( string $slug = '' ) :string {
				return empty( $slug ) ? '/admin/activity/by_plugin' : '/admin/activity/by_plugin?plugin_slug='.$slug;
			}

			public function investigateByTheme( string $slug = '' ) :string {
				return empty( $slug ) ? '/admin/activity/by_theme' : '/admin/activity/by_theme?theme_slug='.$slug;
			}

			public function investigateByCore() :string {
				return '/admin/activity/by_core';
			}
		};
		PluginControllerInstaller::install( $controller );
	}

	private function installServicesStubs(
		array $query = [],
		array $plugins = [],
		array $themes = [],
		?\Closure $ipValidator = null,
		?object $queryCallCount = null,
		?object $optionsBuildCount = null
	) :void {
		ServicesState::installItems( [
			'service_request'   => new class( $query, $queryCallCount ) extends Request {
				private array $queryValues;

				private ?object $queryCallCount;

				public function __construct( array $queryValues = [], ?object $queryCallCount = null ) {
					$this->queryValues = $queryValues;
					$this->queryCallCount = $queryCallCount;
				}

				public function query( $key, $default = null ) {
					if ( $this->queryCallCount !== null ) {
						$this->queryCallCount->count++;
					}
					return $this->queryValues[ $key ] ?? $default;
				}
			},
			'service_ip'        => new class( $ipValidator ) extends IpUtils {
				private ?\Closure $validator;

				public function __construct( ?\Closure $validator ) {
					$this->validator = $validator;
				}

				public function isValidIp( $ip, $flags = null ) {
					return $this->validator instanceof \Closure
						? (bool)( $this->validator )( $ip )
						: \filter_var( $ip, \FILTER_VALIDATE_IP ) !== false;
				}
			},
			'service_wpplugins' => new class( $plugins, $optionsBuildCount ) extends Plugins {
				private array $plugins;

				private ?object $optionsBuildCount;

				public function __construct( array $plugins, ?object $optionsBuildCount = null ) {
					$this->plugins = $plugins;
					$this->optionsBuildCount = $optionsBuildCount;
				}

				public function getPluginsAsVo() :array {
					if ( $this->optionsBuildCount !== null ) {
						$this->optionsBuildCount->plugins++;
					}
					return $this->plugins;
				}
			},
			'service_wpthemes'  => new class( $themes, $optionsBuildCount ) extends Themes {
				private array $themes;

				private ?object $optionsBuildCount;

				public function __construct( array $themes, ?object $optionsBuildCount = null ) {
					$this->themes = $themes;
					$this->optionsBuildCount = $optionsBuildCount;
				}

				public function getThemesAsVo() :array {
					if ( $this->optionsBuildCount !== null ) {
						$this->optionsBuildCount->themes++;
					}
					return $this->themes;
				}
			},
		] );
	}

	private function invokeProtectedMethod( object $subject, string $methodName ) :array {
		$method = new \ReflectionMethod( $subject, $methodName );
		$method->setAccessible( true );
		return $method->invoke( $subject );
	}

}
