<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;

class PageDashboardMetersChannelContractTest extends BaseUnitTest {

	use PluginPathsTrait;

	public function test_dashboard_meters_page_requests_progress_meters_with_config_channel() :void {
		$content = $this->getPluginFileContents(
			'src/ActionRouter/Actions/Render/PluginAdminPages/PageDashboardMeters.php',
			'dashboard meters page class'
		);

		$this->assertStringContainsString( 'ProgressMeters::class', $content );
		$this->assertStringContainsString( "'meter_channel' => MeterComponent::CHANNEL_CONFIG", $content );
	}
}
