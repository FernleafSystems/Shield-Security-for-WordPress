<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Controller\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class PluginNavsOperatorModesTest extends BaseUnitTest {

	public function test_mode_for_nav_returns_expected_modes() :void {
		$this->assertSame( PluginNavs::MODE_ACTIONS, PluginNavs::modeForNav( PluginNavs::NAV_SCANS ) );
		$this->assertSame( PluginNavs::MODE_INVESTIGATE, PluginNavs::modeForNav( PluginNavs::NAV_ACTIVITY ) );
		$this->assertSame( PluginNavs::MODE_CONFIGURE, PluginNavs::modeForNav( PluginNavs::NAV_ZONES ) );
		$this->assertSame( PluginNavs::MODE_REPORTS, PluginNavs::modeForNav( PluginNavs::NAV_REPORTS ) );
	}

	public function test_default_entry_for_mode_returns_expected_nav_subnav_pairs() :void {
		$actions = PluginNavs::defaultEntryForMode( PluginNavs::MODE_ACTIONS );
		$this->assertSame( PluginNavs::NAV_SCANS, $actions[ 'nav' ] );
		$this->assertSame( PluginNavs::SUBNAV_SCANS_RESULTS, $actions[ 'subnav' ] );

		$investigate = PluginNavs::defaultEntryForMode( PluginNavs::MODE_INVESTIGATE );
		$this->assertSame( PluginNavs::NAV_ACTIVITY, $investigate[ 'nav' ] );
		$this->assertSame( PluginNavs::SUBNAV_LOGS, $investigate[ 'subnav' ] );

		$configure = PluginNavs::defaultEntryForMode( PluginNavs::MODE_CONFIGURE );
		$this->assertSame( PluginNavs::NAV_ZONES, $configure[ 'nav' ] );
		$this->assertSame( PluginNavs::GetDefaultSubNavForNav( PluginNavs::NAV_ZONES ), $configure[ 'subnav' ] );

		$reports = PluginNavs::defaultEntryForMode( PluginNavs::MODE_REPORTS );
		$this->assertSame( PluginNavs::NAV_REPORTS, $reports[ 'nav' ] );
		$this->assertSame( PluginNavs::SUBNAV_REPORTS_LIST, $reports[ 'subnav' ] );
	}
}
