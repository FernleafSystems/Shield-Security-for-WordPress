<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\CloakedPlugins;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CloakedPlugins\{
	CloakedPluginFinding,
	CloakReason,
	PluginEntry,
	PluginType
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class CloakedPluginFindingTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
	}

	public function testAlertDataContractIsCompleteAndStable() :void {
		$finding = new CloakedPluginFinding(
			new PluginEntry( PluginType::Standard, 'cloaked/cloaked.php', 'Cloaked Plugin', '1.2.3', '/plugins/cloaked/cloaked.php' ),
			[ CloakReason::AllPlugins, CloakReason::PluginsList ],
			true,
			false,
			123456
		);

		$alertData = $finding->toAlertData();

		$this->assertSame( [
			'type'             => 'plugin',
			'type_label'       => 'Plugin',
			'file'             => 'cloaked/cloaked.php',
			'name'             => 'Cloaked Plugin',
			'version'          => '1.2.3',
			'location'         => 'plugins/cloaked/cloaked.php',
			'status'           => 'active',
			'hidden_by'        => [ 'all_plugins', 'plugins_list' ],
			'hidden_by_labels' => [ 'Removed By all_plugins Filter', 'Removed From Final Plugins List' ],
			'detected_at'      => 123456,
		], $alertData );
	}

	public function testAlertDataUsesRelativeMustUsePluginLocation() :void {
		$finding = new CloakedPluginFinding(
			new PluginEntry( PluginType::MustUse, '/loader.php', 'Loader', '', '/absolute/wp-content/mu-plugins/loader.php' ),
			[ CloakReason::ShowAdvancedPlugins ],
			true,
			false,
			123456
		);

		$alertData = $finding->toAlertData();

		$this->assertSame( 'mu-plugins/loader.php', $alertData[ 'location' ] );
		$this->assertArrayNotHasKey( 'path', $alertData );
	}

	public function testIdentityKeyIsStableForTypeAndFileOnly() :void {
		$first = new CloakedPluginFinding(
			new PluginEntry( PluginType::Standard, 'cloaked/cloaked.php', 'First Name', '1.2.3', '/plugins/cloaked/cloaked.php' ),
			[ CloakReason::AllPlugins ],
			true,
			false,
			123456
		);
		$second = new CloakedPluginFinding(
			new PluginEntry( PluginType::Standard, 'cloaked/cloaked.php', 'Second Name', '9.9.9', '/plugins/cloaked/cloaked.php' ),
			[ CloakReason::PluginsList ],
			false,
			true,
			654321
		);

		$this->assertSame( $first->identityKey(), $second->identityKey() );
		$this->assertNotSame( $first->fingerprint(), $second->fingerprint() );
	}

	public function testAuditParamsContractIsCompleteAndStable() :void {
		$finding = new CloakedPluginFinding(
			new PluginEntry( PluginType::MustUse, 'loader.php', 'Loader', '', '/mu-plugins/loader.php' ),
			[ CloakReason::ShowAdvancedPlugins ],
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

		new PluginEntry( 'unsupported-type', 'cloaked/cloaked.php', 'Cloaked', '1.0', '/plugins/cloaked/cloaked.php' );
	}

	public function testFindingRejectsUnsupportedCloakReason() :void {
		$this->expectException( \InvalidArgumentException::class );

		new CloakedPluginFinding(
			new PluginEntry( PluginType::Standard, 'cloaked/cloaked.php', 'Cloaked Plugin', '1.2.3', '/plugins/cloaked/cloaked.php' ),
			[ 'unsupported-reason' ],
			true,
			false,
			123456
		);
	}
}
