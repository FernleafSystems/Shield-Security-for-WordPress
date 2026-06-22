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
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\Fs;

class CloakedPluginFindingTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'wp_normalize_path' )->alias(
			static fn( string $path ) :string => \str_replace( '\\', '/', $path )
		);
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::mergeItems( [
			'service_wpfs' => new Fs(),
		] );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function testAlertDataContractIsCompleteAndStable() :void {
		$finding = new CloakedPluginFinding(
			new PluginEntry( PluginType::Standard, 'cloaked/cloaked.php', 'Cloaked Plugin', '1.2.3', ABSPATH.'wp-content/plugins/cloaked/cloaked.php' ),
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
			'location'         => 'wp-content/plugins/cloaked/cloaked.php',
			'status'           => 'active',
			'hidden_by'        => [ 'all_plugins', 'plugins_list' ],
			'hidden_by_labels' => [
				'Removed before WordPress built the plugin list',
				'Removed from the final plugin list shown to admins',
			],
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

		$this->assertSame( 'wp-content/mu-plugins/loader.php', $alertData[ 'location' ] );
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
