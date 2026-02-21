<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;

class DashboardOverviewTemplateContractTest extends BaseUnitTest {

	use PluginPathsTrait;

	public function testDashboardOverviewTemplateRendersOperatorModeLandingDirectly() :void {
		$content = $this->getPluginFileContents(
			'templates/twig/wpadmin/plugin_pages/inner/dashboard_overview.twig',
			'dashboard overview template'
		);
		$legacyInclude = 'dashboard_overview'.'_simple'.'_body.twig';

		$this->assertStringContainsString( '{{ content.operator_mode_landing|raw }}', $content );
		$this->assertStringNotContainsString( $legacyInclude, $content );
	}
}
