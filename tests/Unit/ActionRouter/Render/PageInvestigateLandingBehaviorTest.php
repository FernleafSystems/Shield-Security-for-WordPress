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
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	InvokesNonPublicMethods,
	PluginControllerInstaller
};

class PageInvestigateLandingBehaviorTest extends BaseUnitTest {

	use InvokesNonPublicMethods;
	private object $pluginUrlsCapture;

	protected function setUp() :void {
		parent::setUp();
		Functions\when( 'sanitize_text_field' )->alias( fn( $text ) => $text );
		Functions\when( '__' )->alias( fn( string $text ) :string => $text );
		Functions\when( 'sanitize_key' )->alias(
			fn( $text ) => \is_string( $text ) ? \strtolower( \trim( $text ) ) : ''
		);
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_landing_vars_include_direct_navigation_subject_contract() :void {
		$page = new PageInvestigateLanding();
		$vars = $this->invokeProtectedMethod( $page, 'getLandingVars' );
		$subjects = $vars[ 'subjects' ] ?? [];

		$this->assertArrayNotHasKey( 'active_subject', $vars );
		$this->assertArrayNotHasKey( 'input', $vars );
		$this->assertArrayNotHasKey( 'plugin_options', $vars );
		$this->assertArrayNotHasKey( 'theme_options', $vars );
		$this->assertCount( 8, $subjects );

		$subjectsByKey = [];
		foreach ( $subjects as $subject ) {
			$subjectsByKey[ $subject[ 'key' ] ] = $subject;
			foreach ( [ 'key', 'panel_target', 'is_enabled', 'is_disabled', 'href', 'icon_class', 'subject_label', 'subject_description', 'is_pro' ] as $requiredKey ) {
				$this->assertArrayHasKey( $requiredKey, $subject );
			}
			foreach ( [ 'panel_type', 'input_key', 'input_value', 'options_key', 'options', 'lookup_route', 'tab_id', 'is_active' ] as $removedKey ) {
				$this->assertArrayNotHasKey( $removedKey, $subject );
			}
			$this->assertSame( $subject[ 'key' ], $subject[ 'panel_target' ] );
			$this->assertSame( !(bool)$subject[ 'is_enabled' ], (bool)$subject[ 'is_disabled' ] );
		}

		$this->assertSame( '/admin/activity/by_user', $subjectsByKey[ 'users' ][ 'href' ] );
		$this->assertSame( '/admin/activity/by_ip', $subjectsByKey[ 'ips' ][ 'href' ] );
		$this->assertSame( '/admin/activity/by_plugin', $subjectsByKey[ 'plugins' ][ 'href' ] );
		$this->assertSame( '/admin/activity/by_theme', $subjectsByKey[ 'themes' ][ 'href' ] );
		$this->assertSame( '/admin/activity/by_core', $subjectsByKey[ 'wordpress' ][ 'href' ] );
		$this->assertSame( '/admin/traffic/logs', $subjectsByKey[ 'requests' ][ 'href' ] );
		$this->assertSame( '/admin/activity/logs', $subjectsByKey[ 'activity' ][ 'href' ] );
		$this->assertFalse( $subjectsByKey[ 'woocommerce' ][ 'is_enabled' ] );
		$this->assertTrue( $subjectsByKey[ 'woocommerce' ][ 'is_pro' ] );
		$this->assertSame( '', $subjectsByKey[ 'woocommerce' ][ 'href' ] );
	}

	public function test_mode_shell_contract_is_exposed_in_render_data() :void {
		$page = new PageInvestigateLanding();
		$renderData = $this->invokeProtectedMethod( $page, 'getRenderData' );

		$this->assertSame( 'investigate', $renderData[ 'vars' ][ 'mode_shell' ][ 'mode' ] ?? '' );
		$this->assertSame( 'info', $renderData[ 'vars' ][ 'mode_shell' ][ 'accent_status' ] ?? '' );
		$this->assertTrue( (bool)( $renderData[ 'vars' ][ 'mode_shell' ][ 'is_mode_landing' ] ?? false ) );
		$this->assertCount( 8, $renderData[ 'vars' ][ 'mode_tiles' ] ?? [] );
	}

	public function test_landing_strings_exclude_workflow_shell_copy() :void {
		$page = new PageInvestigateLanding();
		$strings = $this->invokeProtectedMethod( $page, 'getLandingStrings' );

		foreach ( [ 'label_pro' ] as $expectedKey ) {
			$this->assertArrayHasKey( $expectedKey, $strings );
		}
		foreach ( [ 'selector_title', 'selector_intro', 'selector_section_label', 'lookup_section_label', 'panel_intro', 'ip_invalid_text' ] as $removedKey ) {
			$this->assertArrayNotHasKey( $removedKey, $strings );
		}
	}

	public function test_landing_flags_are_empty() :void {
		$page = new PageInvestigateLanding();
		$this->assertSame( [], $this->invokeProtectedMethod( $page, 'getLandingFlags' ) );
	}

	public function test_landing_hrefs_include_required_subject_and_log_routes() :void {
		$page = new PageInvestigateLanding();
		$hrefs = $this->invokeProtectedMethod( $page, 'getLandingHrefs' );

		$this->assertSame( '/admin/activity/logs', $hrefs[ 'activity_log' ] ?? '' );
		$this->assertSame( '/admin/traffic/logs', $hrefs[ 'traffic_log' ] ?? '' );
		$this->assertSame( '/admin/activity/by_user', $hrefs[ 'by_user' ] ?? '' );
		$this->assertSame( '/admin/activity/by_ip', $hrefs[ 'by_ip' ] ?? '' );
		$this->assertSame( '/admin/activity/by_plugin', $hrefs[ 'by_plugin' ] ?? '' );
		$this->assertSame( '/admin/activity/by_theme', $hrefs[ 'by_theme' ] ?? '' );
		$this->assertSame( '/admin/activity/by_core', $hrefs[ 'by_core' ] ?? '' );
	}

	public function test_subject_payload_and_hrefs_are_cached_per_instance() :void {
		$page = new PageInvestigateLanding();
		$this->invokeProtectedMethod( $page, 'getLandingVars' );
		$this->invokeProtectedMethod( $page, 'getLandingTiles' );
		$this->invokeProtectedMethod( $page, 'getLandingHrefs' );

		$this->assertSame( 2, $this->pluginUrlsCapture->adminTopNav );
		$this->assertSame( 1, $this->pluginUrlsCapture->investigateByUser );
		$this->assertSame( 1, $this->pluginUrlsCapture->investigateByIp );
		$this->assertSame( 1, $this->pluginUrlsCapture->investigateByPlugin );
		$this->assertSame( 1, $this->pluginUrlsCapture->investigateByTheme );
		$this->assertSame( 1, $this->pluginUrlsCapture->investigateByCore );
	}

	private function installControllerStub() :void {
		$this->pluginUrlsCapture = (object)[
			'adminTopNav'          => 0,
			'investigateByIp'      => 0,
			'investigateByUser'    => 0,
			'investigateByPlugin'  => 0,
			'investigateByTheme'   => 0,
			'investigateByCore'    => 0,
		];

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new class( $this->pluginUrlsCapture ) {
			private object $capture;

			public function __construct( object $capture ) {
				$this->capture = $capture;
			}

			public function rootAdminPageSlug() :string {
				return 'icwp-wpsf-plugin';
			}

			public function adminTopNav( string $nav, string $subnav = '' ) :string {
				$this->capture->adminTopNav++;
				return '/admin/'.$nav.'/'.$subnav;
			}

			public function investigateByIp( string $ip = '' ) :string {
				$this->capture->investigateByIp++;
				return empty( $ip ) ? '/admin/activity/by_ip' : '/admin/activity/by_ip?analyse_ip='.$ip;
			}

			public function investigateByUser( string $lookup = '' ) :string {
				$this->capture->investigateByUser++;
				return empty( $lookup ) ? '/admin/activity/by_user' : '/admin/activity/by_user?user_lookup='.$lookup;
			}

			public function investigateByPlugin( string $slug = '' ) :string {
				$this->capture->investigateByPlugin++;
				return empty( $slug ) ? '/admin/activity/by_plugin' : '/admin/activity/by_plugin?plugin_slug='.$slug;
			}

			public function investigateByTheme( string $slug = '' ) :string {
				$this->capture->investigateByTheme++;
				return empty( $slug ) ? '/admin/activity/by_theme' : '/admin/activity/by_theme?theme_slug='.$slug;
			}

			public function investigateByCore() :string {
				$this->capture->investigateByCore++;
				return '/admin/activity/by_core';
			}
		};
		PluginControllerInstaller::install( $controller );
	}

	private function invokeProtectedMethod( object $subject, string $methodName ) :array {
		return $this->invokeNonPublicMethod( $subject, $methodName );
	}
}
