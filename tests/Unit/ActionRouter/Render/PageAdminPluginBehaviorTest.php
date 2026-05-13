<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PageAdminPluginRouteResolver;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	UnitTestControllerFactory,
	UnitTestZonesComponent
};

class PageAdminPluginBehaviorTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias(
			fn( string $text ) :string => $text
		);
		Functions\when( 'sanitize_key' )->alias(
			fn( string $key ) :string => \strtolower( \trim( $key ) )
		);

		UnitTestControllerFactory::install(
			null,
			null,
			(object)[
				'comps' => (object)[
					'zones' => new UnitTestZonesComponent(),
				],
			]
		);
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_non_activity_route_does_not_include_investigate_context_inputs() :void {
		$route = $this->resolve( [
			Constants::NAV_ID     => PluginNavs::NAV_REPORTS,
			Constants::NAV_SUB_ID => PluginNavs::SUBNAV_REPORTS_OVERVIEW,
			'user_lookup'         => '33',
			'subject'             => 'ip',
		] );

		$this->assertSame( PluginNavs::NAV_REPORTS, $route[ 'nav' ] );
		$this->assertSame( PluginNavs::SUBNAV_REPORTS_OVERVIEW, $route[ 'subnav' ] );
		$this->assertSame( PluginNavs::MODE_REPORTS, $route[ 'mode' ] );
		$this->assertTrue( $route[ 'is_mode_landing' ] );
		$this->assertSame( PluginAdminPages\PageReportsLanding::class, $route[ 'delegate_action' ] );
		$this->assertSame(
			[
				Constants::NAV_ID     => PluginNavs::NAV_REPORTS,
				Constants::NAV_SUB_ID => PluginNavs::SUBNAV_REPORTS_OVERVIEW,
			],
			$route[ 'delegate_payload' ]
		);
	}

	public function test_activity_overview_route_preserves_provided_investigate_context_inputs() :void {
		$route = $this->resolve( [
			Constants::NAV_ID     => PluginNavs::NAV_ACTIVITY,
			Constants::NAV_SUB_ID => PluginNavs::SUBNAV_ACTIVITY_OVERVIEW,
			'user_lookup'         => '33',
			'subject'             => 'ip',
		] );
		$data = $route[ 'delegate_payload' ];

		$this->assertSame( PluginAdminPages\PageInvestigateLanding::class, $route[ 'delegate_action' ] );
		$this->assertSame( '33', $data[ 'user_lookup' ] ?? '' );
		$this->assertSame( 'ip', $data[ 'subject' ] ?? '' );
	}

	public function test_activity_by_user_route_overrides_subject_with_canonical_user_key() :void {
		$route = $this->resolve( [
			Constants::NAV_ID     => PluginNavs::NAV_ACTIVITY,
			Constants::NAV_SUB_ID => PluginNavs::SUBNAV_ACTIVITY_BY_USER,
			'user_lookup'         => 'admin@example.com',
			'subject'             => 'ip',
		] );
		$data = $route[ 'delegate_payload' ];

		$this->assertSame( 'admin@example.com', $data[ 'user_lookup' ] ?? '' );
		$this->assertSame( 'user', $data[ 'subject' ] ?? '' );
	}

	public function test_activity_by_core_route_sets_canonical_core_subject_without_lookup() :void {
		$route = $this->resolve( [
			Constants::NAV_ID     => PluginNavs::NAV_ACTIVITY,
			Constants::NAV_SUB_ID => PluginNavs::SUBNAV_ACTIVITY_BY_CORE,
		] );
		$data = $route[ 'delegate_payload' ];

		$this->assertSame( 'core', $data[ 'subject' ] ?? '' );
	}

	public function test_invalid_route_falls_back_to_dashboard_default_route() :void {
		$route = $this->resolve( [
			Constants::NAV_ID     => 'missing',
			Constants::NAV_SUB_ID => 'missing',
		] );

		$this->assertSame( PluginNavs::NAV_DASHBOARD, $route[ 'nav' ] );
		$this->assertSame( PluginNavs::SUBNAV_DASHBOARD_OVERVIEW, $route[ 'subnav' ] );
		$this->assertSame( PluginAdminPages\PageDashboardOverview::class, $route[ 'delegate_action' ] );
	}

	public function test_non_admin_route_resolves_to_restricted_page() :void {
		$route = $this->resolve(
			[
				Constants::NAV_ID     => PluginNavs::NAV_REPORTS,
				Constants::NAV_SUB_ID => PluginNavs::SUBNAV_REPORTS_OVERVIEW,
			],
			false
		);

		$this->assertSame( PluginNavs::NAV_RESTRICTED, $route[ 'nav' ] );
		$this->assertSame( PluginNavs::SUBNAV_INDEX, $route[ 'subnav' ] );
		$this->assertSame( PluginAdminPages\PageSecurityAdminRestricted::class, $route[ 'delegate_action' ] );
	}

	private function resolve( array $actionData, bool $isPluginAdmin = true ) :array {
		return ( new PageAdminPluginRouteResolver() )->resolve( $actionData, $isPluginAdmin );
	}
}
