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

	public function test_selector_crumb_is_first() :void {
		$crumbs = ( new BuildBreadCrumbsForTest() )->parse(
			PluginNavs::NAV_SCANS,
			PluginNavs::SUBNAV_SCANS_OVERVIEW
		);

		$this->assertSame( 'Shield Security', $crumbs[ 0 ][ 'text' ] );
		$this->assertSame( '/dashboard/overview', $crumbs[ 0 ][ 'href' ] );
	}

	public function test_mode_crumb_is_derived_from_nav() :void {
		$crumbs = ( new BuildBreadCrumbsForTest() )->parse(
			PluginNavs::NAV_SCANS,
			PluginNavs::SUBNAV_SCANS_RESULTS
		);

		$this->assertSame( 'Actions Queue', $crumbs[ 1 ][ 'text' ] );
		$this->assertSame( '/scans/overview', $crumbs[ 1 ][ 'href' ] );
	}

	public function test_mode_landing_subnav_omits_nav_home_crumb() :void {
		$crumbs = ( new BuildBreadCrumbsForTest() )->parse(
			PluginNavs::NAV_SCANS,
			PluginNavs::SUBNAV_SCANS_OVERVIEW
		);

		$this->assertCount( 2, $crumbs );
		$this->assertSame( [ 'Shield Security', 'Actions Queue' ], \array_column( $crumbs, 'text' ) );
	}

	public function test_investigate_landing_subnav_omits_nav_home_crumb() :void {
		$crumbs = ( new BuildBreadCrumbsForTest() )->parse(
			PluginNavs::NAV_ACTIVITY,
			PluginNavs::SUBNAV_ACTIVITY_OVERVIEW
		);

		$this->assertCount( 2, $crumbs );
		$this->assertSame( [ 'Shield Security', 'Investigate' ], \array_column( $crumbs, 'text' ) );
	}

	public function test_investigate_by_ip_subnav_uses_contextual_crumb_label_and_href() :void {
		$crumbs = ( new BuildBreadCrumbsForTest() )->parse(
			PluginNavs::NAV_ACTIVITY,
			PluginNavs::SUBNAV_ACTIVITY_BY_IP
		);

		$this->assertCount( 3, $crumbs );
		$this->assertSame( [ 'Shield Security', 'Investigate', 'IP Addresses' ], \array_column( $crumbs, 'text' ) );
		$this->assertSame( '/activity/by_ip', $crumbs[ 2 ][ 'href' ] ?? '' );
		$this->assertSame( 'Navigation: IP Addresses', $crumbs[ 2 ][ 'title' ] ?? '' );
	}

	public function test_investigate_by_user_subnav_uses_contextual_crumb_label_and_href() :void {
		$crumbs = ( new BuildBreadCrumbsForTest() )->parse(
			PluginNavs::NAV_ACTIVITY,
			PluginNavs::SUBNAV_ACTIVITY_BY_USER
		);

		$this->assertCount( 3, $crumbs );
		$this->assertSame( [ 'Shield Security', 'Investigate', 'Users' ], \array_column( $crumbs, 'text' ) );
		$this->assertSame( '/activity/by_user', $crumbs[ 2 ][ 'href' ] ?? '' );
		$this->assertSame( 'Navigation: Users', $crumbs[ 2 ][ 'title' ] ?? '' );
	}

	public function test_investigate_asset_subnavs_use_contextual_crumb_labels_and_hrefs() :void {
		$builder = new BuildBreadCrumbsForTest();

		$expected = [
			PluginNavs::SUBNAV_ACTIVITY_BY_PLUGIN => 'Plugins',
			PluginNavs::SUBNAV_ACTIVITY_BY_THEME  => 'Themes',
			PluginNavs::SUBNAV_ACTIVITY_BY_CORE   => 'WordPress Core',
		];
		foreach ( $expected as $subNav => $label ) {
			$crumbs = $builder->parse( PluginNavs::NAV_ACTIVITY, $subNav );
			$this->assertCount( 3, $crumbs );
			$this->assertSame( [ 'Shield Security', 'Investigate', $label ], \array_column( $crumbs, 'text' ) );
			$this->assertSame( '/activity/'.$subNav, $crumbs[ 2 ][ 'href' ] ?? '' );
			$this->assertSame( 'Navigation: '.$label, $crumbs[ 2 ][ 'title' ] ?? '' );
		}
	}

	public function test_investigate_logs_subnav_uses_activity_log_crumb_label_and_href() :void {
		$crumbs = ( new BuildBreadCrumbsForTest() )->parse(
			PluginNavs::NAV_ACTIVITY,
			PluginNavs::SUBNAV_LOGS
		);

		$this->assertCount( 3, $crumbs );
		$this->assertSame( [ 'Shield Security', 'Investigate', 'Activity Log' ], \array_column( $crumbs, 'text' ) );
		$this->assertSame( '/activity/logs', $crumbs[ 2 ][ 'href' ] ?? '' );
		$this->assertSame( 'Navigation: Activity Log', $crumbs[ 2 ][ 'title' ] ?? '' );
	}

	public function test_reports_non_landing_subnavs_keep_nav_home_crumb() :void {
		$builder = new BuildBreadCrumbsForTest();

		foreach ( [
			PluginNavs::SUBNAV_REPORTS_LIST,
			PluginNavs::SUBNAV_REPORTS_CHARTS,
			PluginNavs::SUBNAV_REPORTS_SETTINGS,
		] as $subNav ) {
			$crumbs = $builder->parse( PluginNavs::NAV_REPORTS, $subNav );
			$this->assertCount( 3, $crumbs );
			$this->assertSame( [ 'Shield Security', 'Reports', 'Reports' ], \array_column( $crumbs, 'text' ) );
			$this->assertSame( '/reports/overview', $crumbs[ 2 ][ 'href' ] ?? '' );
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
		];
	}

	protected function buildNavUrl( string $nav, string $subNav ) :string {
		return '/'.$nav.'/'.$subNav;
	}

	protected function getDefaultSubNavForNav( string $nav, array $hierarchy ) :string {
		return \key( $hierarchy[ $nav ][ 'sub_navs' ] );
	}
}
