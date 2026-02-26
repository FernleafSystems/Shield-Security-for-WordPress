<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;

class PageDashboardOverviewRenderContractTest extends BaseUnitTest {

	use PluginPathsTrait;

	public function test_dashboard_overview_page_only_prepares_operator_mode_landing() :void {
		$content = $this->getPluginFileContents(
			'src/ActionRouter/Actions/Render/PluginAdminPages/PageDashboardOverview.php',
			'dashboard overview page class'
		);

		$this->assertStringContainsString( "'operator_mode_landing'", $content );
		$this->assertStringContainsString( 'PageOperatorModeLanding::class', $content );
		$this->assertStringNotContainsString( 'ChartsSummary::class', $content );
		$this->assertStringNotContainsString( 'OverviewActivity::class', $content );
		$this->assertStringNotContainsString( 'OverviewTraffic::class', $content );
	}
}
