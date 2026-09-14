<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\CloakedPlugins;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CloakedPlugins\{
	AdminPluginVisibility,
	PluginType
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class AdminPluginVisibilityTest extends BaseUnitTest {

	private const PLUGINS = [ 'visible/visible.php' => [ 'Name' => 'Visible' ] ];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( 'get_plugins' )->justReturn( self::PLUGINS );
		Functions\when( 'get_mu_plugins' )->justReturn( [] );
	}

	public function testBackgroundSnapshotDoesNotReplayFiltersOrReplacePageFindings() :void {
		Functions\expect( 'apply_filters' )->never();
		update_option( 'active_plugins', [ 'visible/visible.php', false ] );
		update_site_option( 'active_sitewide_plugins', [ 'network/network.php' => 123 ] );

		$snapshot = ( new AdminPluginVisibility() )->snapshot();

		$this->assertFalse( $snapshot->isPageObservation );
		$this->assertTrue( $snapshot->canCompare( PluginType::Standard ) );
		$this->assertFalse( $snapshot->canReplaceFindings( PluginType::Standard ) );
		$this->assertFalse( $snapshot->canReplaceFindings( PluginType::MustUse ) );
		$this->assertSame( [ 'visible/visible.php' ], $snapshot->activePlugins );
		$this->assertSame( [ 'network/network.php' ], $snapshot->networkActivePlugins );
	}

	public function testCompletedObservationIsConsumedAndCannotLeakIntoTheNextList() :void {
		$visibility = new AdminPluginVisibility();
		$final = \array_fill_keys( [ 'all', 'active', 'inactive', 'recently_activated', 'upgrade', 'paused', 'mustuse' ], [] );
		$final[ 'all' ] = self::PLUGINS;
		$final[ 'inactive' ] = self::PLUGINS;

		$this->assertSame( self::PLUGINS, $visibility->beginPluginsList( self::PLUGINS ) );
		$this->assertSame( self::PLUGINS, $visibility->observeAllPlugins( self::PLUGINS ) );
		$this->assertTrue( $visibility->observeAdvancedPlugins( true, 'mustuse' ) );
		$completed = $visibility->finishPluginsList( $final );
		$this->assertNotNull( $completed );
		$this->assertTrue( $completed->canReplaceFindings( PluginType::Standard ) );
		$this->assertTrue( $completed->canReplaceFindings( PluginType::MustUse ) );
		$this->assertNull( $visibility->finishPluginsList( $final ) );

		$visibility->beginPluginsList( self::PLUGINS );
		$this->assertFalse( $visibility->observeAdvancedPlugins( false, 'dropins' ) );
		$incomplete = $visibility->finishPluginsList( $final );
		$this->assertNotNull( $incomplete );
		$this->assertNull( $incomplete->adminAllPlugins );
		$this->assertNull( $incomplete->showMustUsePlugins );
		$this->assertFalse( $incomplete->canReplaceFindings( PluginType::Standard ) );
		$this->assertFalse( $incomplete->canReplaceFindings( PluginType::MustUse ) );
		$this->assertTrue( $completed->canReplaceFindings( PluginType::Standard ) );
	}

	public function testMalformedCallbackValuesPassThroughWithoutBecomingEvidence() :void {
		$visibility = new AdminPluginVisibility();
		$this->assertFalse( $visibility->beginPluginsList( false ) );
		$this->assertSame( 'invalid', $visibility->observeAllPlugins( 'invalid' ) );
		$this->assertSame( 1, $visibility->observeAdvancedPlugins( 1, 'mustuse' ) );
		$snapshot = $visibility->finishPluginsList( false );

		$this->assertNotNull( $snapshot );
		$this->assertNull( $snapshot->wpDiscoveredPlugins );
		$this->assertNull( $snapshot->adminAllPlugins );
		$this->assertNull( $snapshot->showMustUsePlugins );
		$this->assertNull( $snapshot->finalPluginsList );
		$this->assertFalse( $snapshot->canCompare( PluginType::Standard ) );
		$this->assertFalse( $snapshot->canCompare( PluginType::MustUse ) );
	}
}
