<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;

class NavSidebarModeBackLinkStyleTest extends BaseUnitTest {

	use PluginPathsTrait;

	public function testModeBackLinkSelectorIsScopedToTopLevelNavLink() :void {
		$content = $this->getNavSidebarStylesheetContents();

		$this->assertStringContainsString(
			'#NavSideBar .nav-item > .nav-link.mode-back-link {',
			$content
		);
	}

	public function testModeBackLinkSubtitleIsSuppressed() :void {
		$content = $this->getNavSidebarStylesheetContents();

		$this->assertStringContainsString(
			'#NavSideBar .nav-item > .nav-link.mode-back-link .subtitle {',
			$content
		);
		$this->assertStringContainsString( 'display: none;', $content );
	}

	public function testModeBackLinkHasMutedBaseAndHoverStyles() :void {
		$content = $this->getNavSidebarStylesheetContents();

		$this->assertStringContainsString( 'font-size: 0.8rem;', $content );
		$this->assertStringContainsString( 'color: #5f6974;', $content );
		$this->assertStringContainsString( 'background: #f8f9f8;', $content );
		$this->assertStringContainsString(
			'#NavSideBar .nav-item > .nav-link.mode-back-link:hover {',
			$content
		);
		$this->assertStringContainsString( 'background: #f7f9f7;', $content );
	}

	public function testMenuGroupBoundarySeparatorStylesExist() :void {
		$content = $this->getNavSidebarStylesheetContents();

		$this->assertStringContainsString(
			'#NavSideBar .nav-item > .nav-link.menu-group-break-before {',
			$content
		);
		$this->assertStringContainsString(
			'#NavSideBar .nav-item > .nav-link.menu-group-break-before::before {',
			$content
		);
		$this->assertStringContainsString( '$accent-color-salt-green-soft', $content );
	}

	private function getNavSidebarStylesheetContents() :string {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'assets/css source stylesheets are excluded from packaged artifacts' );
		}

		return $this->getPluginFileContents(
			'assets/css/components/nav_sidebar_menu.scss',
			'sidebar navigation stylesheet'
		);
	}
}
