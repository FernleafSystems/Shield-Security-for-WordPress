<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\HiddenPlugins;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\HiddenPlugins\{
	HiddenPluginFinding,
	HiddenReason,
	PluginEntry,
	PluginType
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class HiddenPluginFindingTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
	}

	public function testAlertDataContractIsCompleteAndStable() :void {
		$finding = new HiddenPluginFinding(
			new PluginEntry( PluginType::Standard, 'hidden/hidden.php', 'Hidden Plugin', '1.2.3', '/plugins/hidden/hidden.php' ),
			[ HiddenReason::AllPlugins, HiddenReason::PluginsList ],
			true,
			false,
			123456
		);

		$alertData = $finding->toAlertData();

		$this->assertSame( [
			'type'             => 'plugin',
			'type_label'       => 'Plugin',
			'file'             => 'hidden/hidden.php',
			'name'             => 'Hidden Plugin',
			'version'          => '1.2.3',
			'location'         => 'plugins/hidden/hidden.php',
			'status'           => 'active',
			'hidden_by'        => [ 'all_plugins', 'plugins_list' ],
			'hidden_by_labels' => [ 'Removed By all_plugins Filter', 'Removed From Final Plugins List' ],
			'detected_at'      => 123456,
		], $alertData );
	}

	public function testAlertDataUsesRelativeMustUsePluginLocation() :void {
		$finding = new HiddenPluginFinding(
			new PluginEntry( PluginType::MustUse, '/loader.php', 'Loader', '', '/absolute/wp-content/mu-plugins/loader.php' ),
			[ HiddenReason::ShowAdvancedPlugins ],
			true,
			false,
			123456
		);

		$alertData = $finding->toAlertData();

		$this->assertSame( 'mu-plugins/loader.php', $alertData[ 'location' ] );
		$this->assertArrayNotHasKey( 'path', $alertData );
	}

	public function testAuditParamsContractIsCompleteAndStable() :void {
		$finding = new HiddenPluginFinding(
			new PluginEntry( PluginType::MustUse, 'loader.php', 'Loader', '', '/mu-plugins/loader.php' ),
			[ HiddenReason::ShowAdvancedPlugins ],
			true,
			true,
			123456
		);

		$this->assertSame( [
			'plugin'    => 'loader.php',
			'type'      => 'mu-plugin',
			'hidden_by' => 'show_advanced_plugins',
			'status'    => 'must-use',
			'name'      => 'Loader',
			'version'   => '',
		], $finding->toAuditParams() );
	}

	public function testPluginEntryRejectsUnsupportedType() :void {
		$this->expectException( \InvalidArgumentException::class );

		new PluginEntry( 'unsupported-type', 'hidden/hidden.php', 'Hidden', '1.0', '/plugins/hidden/hidden.php' );
	}

	public function testFindingRejectsUnsupportedHiddenReason() :void {
		$this->expectException( \InvalidArgumentException::class );

		new HiddenPluginFinding(
			new PluginEntry( PluginType::Standard, 'hidden/hidden.php', 'Hidden Plugin', '1.2.3', '/plugins/hidden/hidden.php' ),
			[ 'unsupported-reason' ],
			true,
			false,
			123456
		);
	}
}
