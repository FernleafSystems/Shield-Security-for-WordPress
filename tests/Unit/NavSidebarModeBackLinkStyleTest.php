<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;

class NavSidebarModeBackLinkStyleTest extends BaseUnitTest {

	use PluginPathsTrait;

	public function testModeBackLinkSelectorIsScopedToTopLevelNavLink() :void {
		$content = $this->getPluginFileContents(
			'assets/css/components/nav_sidebar_menu.scss',
			'sidebar navigation stylesheet'
		);

		$this->assertStringContainsString(
			'#NavSideBar .nav-item > .nav-link.mode-back-link {',
			$content
		);
	}

	public function testModeBackLinkSubtitleIsSuppressed() :void {
		$content = $this->getPluginFileContents(
			'assets/css/components/nav_sidebar_menu.scss',
			'sidebar navigation stylesheet'
		);

		$this->assertStringContainsString(
			'#NavSideBar .nav-item > .nav-link.mode-back-link .subtitle {',
			$content
		);
		$this->assertStringContainsString( 'display: none;', $content );
	}

	public function testModeBackLinkHasMutedBaseAndHoverStyles() :void {
		$content = $this->getPluginFileContents(
			'assets/css/components/nav_sidebar_menu.scss',
			'sidebar navigation stylesheet'
		);

		$this->assertStringContainsString( 'font-size: 0.8rem;', $content );
		$this->assertStringContainsString( 'color: #5f6974;', $content );
		$this->assertStringContainsString(
			'#NavSideBar .nav-item > .nav-link.mode-back-link:hover {',
			$content
		);
		$this->assertStringContainsString( 'background: #f7f9f7;', $content );
	}
}
