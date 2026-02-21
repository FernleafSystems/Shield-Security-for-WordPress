<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;

class AssetsCustomizerProgressMetersContractTest extends BaseUnitTest {

	use PluginPathsTrait;

	public function testProgressMetersIsEnabledForConfigureLanding() :void {
		$content = $this->getPluginFileContents(
			'src/Modules/Plugin/Lib/AssetsCustomizer.php',
			'assets customizer class'
		);

		$this->assertStringContainsString( 'PluginNavs::GetNav() === PluginNavs::NAV_DASHBOARD', $content );
		$this->assertStringContainsString( 'PluginNavs::IsNavs( PluginNavs::NAV_ZONES, PluginNavs::SUBNAV_ZONES_OVERVIEW )', $content );
	}
}
