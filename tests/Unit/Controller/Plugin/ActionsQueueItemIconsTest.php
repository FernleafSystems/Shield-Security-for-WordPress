<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Controller\Plugin;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\{
	ActionsQueueItemIcons,
	PluginNavs
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ActionsQueueItemIconsTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
	}

	public function test_provider_owns_scan_and_maintenance_icon_resolution() :void {
		$icons = new ActionsQueueItemIcons();

		$this->assertSame( 'shield-exclamation', $icons->iconForScanKey( 'vulnerabilities' ) );
		$this->assertSame( 'archive', $icons->iconForScanKey( 'abandoned' ) );
		$this->assertSame( 'archive', $icons->iconForKey( 'abandoned' ) );
		$this->assertSame( 'archive', PluginNavs::actionsLandingScanRowIcon( 'abandoned' ) );
		$this->assertSame( 'bi bi-plug-fill', $icons->iconClassForScanKey( 'plugins' ) );
		$this->assertSame( 'bi bi-archive-fill', $icons->iconClassForScanKey( 'abandoned' ) );
		$this->assertSame( 'bi bi-code-slash', $icons->iconClassForKey( 'system_php_version' ) );
		$this->assertSame( $icons->iconClassForScanKey( 'plugins' ), PluginNavs::actionsLandingScanRailIconClass( 'plugins' ) );
	}
}
