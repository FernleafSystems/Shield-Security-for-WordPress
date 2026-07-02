<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\CloakedPlugins;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CloakedPlugins\{
	CloakedPluginFinding,
	CloakReason,
	PluginEntry,
	PluginType
};
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\MU\{
	GeneratedMuLoaderContent,
	MUHandler
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestControllerFactory
};
use FernleafSystems\Wordpress\Services\Core\Fs;

class CloakedPluginFindingTest extends BaseUnitTest {

	use TempDirLifecycleTrait;

	private array $servicesSnapshot = [];

	private const ROOT_FILE = 'vfs/wp-content/plugins/wp-simple-firewall/icwp-wpsf.php';

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
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		$this->cleanupTrackedTempDirs();
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

		$this->assertSame(
			[ 'type', 'type_label', 'file', 'name', 'version', 'location', 'status', 'hidden_by', 'hidden_by_labels', 'detected_at' ],
			\array_keys( $alertData )
		);
		$this->assertSame( 'plugin', $alertData[ 'type' ] );
		$this->assertNotEmpty( $alertData[ 'type_label' ] );
		$this->assertSame( 'cloaked/cloaked.php', $alertData[ 'file' ] );
		$this->assertSame( 'Cloaked Plugin', $alertData[ 'name' ] );
		$this->assertSame( '1.2.3', $alertData[ 'version' ] );
		$this->assertSame( 'wp-content/plugins/cloaked/cloaked.php', $alertData[ 'location' ] );
		$this->assertSame( 'active', $alertData[ 'status' ] );
		$this->assertSame( [ 'all_plugins', 'plugins_list' ], $alertData[ 'hidden_by' ] );
		$this->assertCount( 2, $alertData[ 'hidden_by_labels' ] );
		$this->assertNotContains( '', $alertData[ 'hidden_by_labels' ] );
		$this->assertSame( 123456, $alertData[ 'detected_at' ] );
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

	public function testShieldGeneratedMuLoaderUsesDedicatedReasonPayload() :void {
		UnitTestControllerFactory::install( null, null, (object)[
			'opts'      => new CloakedPluginFindingOptionsStub( true ),
			'root_file' => self::ROOT_FILE,
			'labels'    => $this->labels(),
		] );
		$path = $this->writeShieldMuLoader();
		$finding = new CloakedPluginFinding(
			new PluginEntry( PluginType::MustUse, MUHandler::PLUGIN_FILE_NAME, 'Shield MU', '', $path ),
			[ CloakReason::ShowAdvancedPlugins ],
			true,
			false,
			123456
		);

		$this->assertSame( [ CloakReason::ShowAdvancedPlugins ], $finding->toAlertData()[ 'hidden_by' ] );
		$this->assertCount( 1, $finding->cloakReasonLabels() );
		$this->assertNotSame( '', $finding->cloakReasonLabels()[ 0 ] );
		$this->assertSame( $finding->cloakReasonLabels(), $finding->toAlertData()[ 'hidden_by_labels' ] );
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

	private function writeShieldMuLoader() :string {
		$dir = $this->createTrackedTempDir( 'shield-generated-mu-' );
		$path = $dir.'/'.MUHandler::PLUGIN_FILE_NAME;
		$this->assertNotFalse( \file_put_contents(
			$path,
			( new GeneratedMuLoaderContent() )->build()
		) );
		return $path;
	}

	private function labels() :object {
		return (object)[
			'Name'      => 'Shield',
			'PluginURI' => 'https://example.test/shield',
			'Author'    => 'Shield',
		];
	}
}

class CloakedPluginFindingOptionsStub {

	private bool $muEnabled;

	public function __construct( bool $muEnabled ) {
		$this->muEnabled = $muEnabled;
	}

	public function optIs( string $key, $value ) :bool {
		return $key === 'enable_mu' && ( $this->muEnabled ? 'Y' : 'N' ) === $value;
	}
}
