<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Utilities\Navigation;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Navigation\BuildBreadCrumbs;

class BuildBreadCrumbsOperatorModesTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias(
			fn( string $text ) :string => $text
		);
	}

	private function assertNoSelfRouteHref( array $crumbs, string $nav, string $subNav ) :void {
		$this->assertNotContains( '/'.$nav.'/'.$subNav, \array_column( $crumbs, 'href' ) );
	}

	public function test_selector_crumb_is_first_for_scans_overview_route() :void {
		$crumbs = ( new BuildBreadCrumbsForTest() )->parse(
			PluginNavs::NAV_SCANS,
			PluginNavs::SUBNAV_SCANS_OVERVIEW
		);

		$this->assertCount( 1, $crumbs );
		$this->assertSame( 'Shield Security', $crumbs[ 0 ][ 'text' ] );
		$this->assertSame( '/dashboard/overview', $crumbs[ 0 ][ 'href' ] );
		$this->assertNoSelfRouteHref( $crumbs, PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_OVERVIEW );
	}

	public function test_mode_crumb_is_derived_from_nav_for_non_landing_route() :void {
		$crumbs = ( new BuildBreadCrumbsForTest() )->parse(
			PluginNavs::NAV_SCANS,
			PluginNavs::SUBNAV_SCANS_RESULTS
		);

		$this->assertCount( 2, $crumbs );
		$this->assertSame( [ 'Shield Security', 'Actions Queue' ], \array_column( $crumbs, 'text' ) );
		$this->assertSame( 'Actions Queue', $crumbs[ 1 ][ 'text' ] );
		$this->assertSame( '/scans/overview', $crumbs[ 1 ][ 'href' ] );
		$this->assertNoSelfRouteHref( $crumbs, PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RESULTS );
	}

	public function test_required_route_matrix_has_no_self_route_breadcrumb_hrefs() :void {
		$builder = new BuildBreadCrumbsForTest();

		foreach ( [
			[
				'nav'            => PluginNavs::NAV_DASHBOARD,
				'subNav'         => PluginNavs::SUBNAV_DASHBOARD_OVERVIEW,
				'expected_texts' => [],
			],
			[
				'nav'            => PluginNavs::NAV_DASHBOARD,
				'subNav'         => PluginNavs::SUBNAV_DASHBOARD_GRADES,
				'expected_texts' => [ 'Shield Security', 'Configure' ],
			],
			[
				'nav'            => PluginNavs::NAV_SCANS,
				'subNav'         => PluginNavs::SUBNAV_SCANS_OVERVIEW,
				'expected_texts' => [ 'Shield Security' ],
			],
			[
				'nav'            => PluginNavs::NAV_ACTIVITY,
				'subNav'         => PluginNavs::SUBNAV_ACTIVITY_OVERVIEW,
				'expected_texts' => [ 'Shield Security' ],
			],
			[
				'nav'            => PluginNavs::NAV_ACTIVITY,
				'subNav'         => PluginNavs::SUBNAV_ACTIVITY_BY_USER,
				'expected_texts' => [ 'Shield Security', 'Investigate' ],
			],
			[
				'nav'            => PluginNavs::NAV_ACTIVITY,
				'subNav'         => PluginNavs::SUBNAV_LOGS,
				'expected_texts' => [ 'Shield Security', 'Investigate' ],
			],
			[
				'nav'            => PluginNavs::NAV_TRAFFIC,
				'subNav'         => PluginNavs::SUBNAV_LOGS,
				'expected_texts' => [ 'Shield Security', 'Investigate' ],
			],
			[
				'nav'            => PluginNavs::NAV_TRAFFIC,
				'subNav'         => PluginNavs::SUBNAV_LIVE,
				'expected_texts' => [ 'Shield Security', 'Investigate' ],
			],
		] as $route ) {
			$crumbs = $builder->parse( $route[ 'nav' ], $route[ 'subNav' ] );
			$this->assertSame( $route[ 'expected_texts' ], \array_column( $crumbs, 'text' ) );
			$this->assertNoSelfRouteHref( $crumbs, $route[ 'nav' ], $route[ 'subNav' ] );
		}
	}

	public function test_reports_non_landing_subnavs_keep_reports_mode_ancestor() :void {
		$builder = new BuildBreadCrumbsForTest();

		foreach ( [
			PluginNavs::SUBNAV_REPORTS_LIST,
			PluginNavs::SUBNAV_REPORTS_CHARTS,
			PluginNavs::SUBNAV_REPORTS_SETTINGS,
		] as $subNav ) {
			$crumbs = $builder->parse( PluginNavs::NAV_REPORTS, $subNav );
			$this->assertCount( 2, $crumbs );
			$this->assertSame( [ 'Shield Security', 'Reports' ], \array_column( $crumbs, 'text' ) );
			$this->assertSame( '/reports/overview', $crumbs[ 1 ][ 'href' ] ?? '' );
			$this->assertNoSelfRouteHref( $crumbs, PluginNavs::NAV_REPORTS, $subNav );
		}
	}

	public function test_scans_non_landing_subnavs_keep_actions_queue_mode_ancestor() :void {
		$builder = new BuildBreadCrumbsForTest();

		foreach ( [
			PluginNavs::SUBNAV_SCANS_RESULTS,
			PluginNavs::SUBNAV_SCANS_RUN,
		] as $subNav ) {
			$crumbs = $builder->parse( PluginNavs::NAV_SCANS, $subNav );
			$this->assertCount( 2, $crumbs );
			$this->assertSame( [ 'Shield Security', 'Actions Queue' ], \array_column( $crumbs, 'text' ) );
			$this->assertSame( '/scans/overview', $crumbs[ 1 ][ 'href' ] ?? '' );
			$this->assertNoSelfRouteHref( $crumbs, PluginNavs::NAV_SCANS, $subNav );
		}
	}

	public function test_investigate_and_ips_supporting_routes_keep_investigate_mode_ancestor() :void {
		$builder = new BuildBreadCrumbsForTest();

		foreach ( [
			[ PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_BY_IP ],
			[ PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_BY_PLUGIN ],
			[ PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_BY_THEME ],
			[ PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_BY_CORE ],
			[ PluginNavs::NAV_IPS, PluginNavs::SUBNAV_IPS_RULES ],
		] as $route ) {
			$crumbs = $builder->parse( $route[ 0 ], $route[ 1 ] );
			$this->assertCount( 2, $crumbs );
			$this->assertSame( [ 'Shield Security', 'Investigate' ], \array_column( $crumbs, 'text' ) );
			$this->assertSame( '/activity/overview', $crumbs[ 1 ][ 'href' ] ?? '' );
			$this->assertNoSelfRouteHref( $crumbs, $route[ 0 ], $route[ 1 ] );
		}
	}
}

class BuildBreadCrumbsForTest extends BuildBreadCrumbs {

	protected function getNavHierarchy() :array {
		return [
			PluginNavs::NAV_DASHBOARD => [
				'name'     => 'Dashboard',
				'sub_navs' => [
					PluginNavs::SUBNAV_DASHBOARD_OVERVIEW => [ 'handler' => 'handler' ],
					PluginNavs::SUBNAV_DASHBOARD_GRADES   => [ 'handler' => 'handler' ],
				],
			],
			PluginNavs::NAV_SCANS     => [
				'name'     => 'Scans',
				'sub_navs' => [
					PluginNavs::SUBNAV_SCANS_OVERVIEW => [ 'handler' => 'handler' ],
					PluginNavs::SUBNAV_SCANS_RESULTS => [ 'handler' => 'handler' ],
					PluginNavs::SUBNAV_SCANS_RUN => [ 'handler' => 'handler' ],
				],
			],
			PluginNavs::NAV_IPS       => [
				'name'     => 'IPs',
				'sub_navs' => [
					PluginNavs::SUBNAV_IPS_RULES => [ 'handler' => 'handler' ],
				],
			],
			PluginNavs::NAV_ACTIVITY  => [
				'name'     => 'Activity',
				'sub_navs' => [
					PluginNavs::SUBNAV_ACTIVITY_OVERVIEW => [ 'handler' => 'handler' ],
					PluginNavs::SUBNAV_ACTIVITY_BY_USER  => [ 'handler' => 'handler' ],
					PluginNavs::SUBNAV_ACTIVITY_BY_IP    => [ 'handler' => 'handler' ],
					PluginNavs::SUBNAV_ACTIVITY_BY_PLUGIN => [ 'handler' => 'handler' ],
					PluginNavs::SUBNAV_ACTIVITY_BY_THEME  => [ 'handler' => 'handler' ],
					PluginNavs::SUBNAV_ACTIVITY_BY_CORE   => [ 'handler' => 'handler' ],
					PluginNavs::SUBNAV_LOGS              => [ 'handler' => 'handler' ],
				],
			],
			PluginNavs::NAV_ZONES     => [
				'name'     => 'Security Zones',
				'sub_navs' => [
					PluginNavs::SUBNAV_ZONES_OVERVIEW => [ 'handler' => 'handler' ],
					'secadmin'                        => [ 'handler' => 'handler' ],
				],
			],
			PluginNavs::NAV_REPORTS   => [
				'name'     => 'Reports',
				'sub_navs' => [
					PluginNavs::SUBNAV_REPORTS_OVERVIEW => [ 'handler' => 'handler' ],
					PluginNavs::SUBNAV_REPORTS_LIST => [ 'handler' => 'handler' ],
					PluginNavs::SUBNAV_REPORTS_CHARTS => [ 'handler' => 'handler' ],
					PluginNavs::SUBNAV_REPORTS_SETTINGS => [ 'handler' => 'handler' ],
				],
			],
			PluginNavs::NAV_TRAFFIC   => [
				'name'     => 'Traffic',
				'sub_navs' => [
					PluginNavs::SUBNAV_LOGS => [ 'handler' => 'handler' ],
					PluginNavs::SUBNAV_LIVE => [ 'handler' => 'handler' ],
				],
			],
		];
	}

	protected function buildNavUrl( string $nav, string $subNav ) :string {
		return '/'.$nav.'/'.$subNav;
	}

	protected function getDefaultSubNavForNav( string $nav, array $hierarchy ) :string {
		return \key( $hierarchy[ $nav ][ 'sub_navs' ] );
	}
}
