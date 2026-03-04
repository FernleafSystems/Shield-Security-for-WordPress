<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;

class SidebarShellStyleContractTest extends BaseUnitTest {

	use PluginPathsTrait;

	public function testSidebarShellUsesLightLegacyContract() :void {
		$content = $this->getPluginMainStylesheetContents();

		$this->assertStringContainsString( '$sidebar-width-expanded: 230px;', $content );
		$this->assertStringContainsString( '#PageMainSide-Apto {', $content );
		$this->assertStringContainsString( 'background-color: transparent;', $content );
		$this->assertStringContainsString( 'background-color: #f1f1f1;', $content );
		$this->assertStringContainsString( 'box-shadow: 3px 0 12px rgba(0, 0, 0, 0.15);', $content );
		$this->assertStringContainsString( 'padding: 5px 20px 8px;', $content );
		$this->assertStringContainsString( 'color: #b7b7b7 !important;', $content );
		$this->assertStringContainsString( '#PageMainSide-Apto > .sidebar-inner > .mt-2.text-center {', $content );
		$this->assertStringContainsString( '#PageMainSide-Apto:hover > .sidebar-inner > .mt-2.text-center {', $content );
		$this->assertStringNotContainsString( '@import \'./shield/sidebar-theme\';', $content );
		$this->assertStringNotContainsString( '$sidebar-bg: #1d2327;', $content );
	}

	public function testSidebarSuperSearchUsesLegacyLightControls() :void {
		$content = $this->getPluginMainStylesheetContents();

		$this->assertStringContainsString( '#SuperSearchLaunch > input {', $content );
		$this->assertStringContainsString( 'border: 2px solid rgba(0, 128, 0, 0.1);', $content );
		$this->assertStringContainsString( 'fill=\'currentColor\'', $content );
		$this->assertStringContainsString( '#SuperSearchLaunch > input:hover {', $content );
		$this->assertStringContainsString( 'border: 2px solid rgba(0, 128, 0, 1);', $content );
		$this->assertStringContainsString( '#SuperSearchLaunch > input.disabled {', $content );
		$this->assertStringContainsString( 'border: 2px solid transparent;', $content );
		$this->assertStringNotContainsString( 'fill=\'%23a7aaad\'', $content );
	}

	private function getPluginMainStylesheetContents() :string {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'assets/css source stylesheets are excluded from packaged artifacts' );
		}

		return $this->getPluginFileContents(
			'assets/css/plugin-main.scss',
			'plugin main stylesheet'
		);
	}
}
