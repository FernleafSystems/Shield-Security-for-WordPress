<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\CloakedPlugins;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CloakedPlugins\{
	CloakedPluginFinding,
	CloakedPluginState,
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
	UnitTestControllerFactory
};

class CloakedPluginStateTest extends BaseUnitTest {

	use TempDirLifecycleTrait;

	private CloakedPluginStateOptionsStub $opts;

	private const ROOT_FILE = 'vfs/wp-content/plugins/wp-simple-firewall/icwp-wpsf.php';

	protected function setUp() :void {
		parent::setUp();
		$this->opts = new CloakedPluginStateOptionsStub();
		UnitTestControllerFactory::install( null, null, (object)[
			'opts'      => $this->opts,
			'root_file' => self::ROOT_FILE,
			'labels'    => $this->labels(),
		] );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testRememberNewReturnsFindingOnlyOncePerFingerprint() :void {
		$state = new CloakedPluginState();
		$finding = $this->finding( 'cloaked/cloaked.php' );

		$this->assertSame( [ $finding ], $state->rememberNew( [ $finding ] ) );
		$this->assertSame( [], $state->rememberNew( [ $finding ] ) );
		$this->assertCount( 1, $this->opts->values[ CloakedPluginState::OPT_KEY ] );
	}

	public function testResolvedFindingsAreRemovedFromStateSoReappearingFindingsAlertAgain() :void {
		$state = new CloakedPluginState();
		$finding = $this->finding( 'cloaked/cloaked.php' );

		$state->rememberNew( [ $finding ] );
		$state->rememberNew( [] );

		$this->assertSame( [ $finding ], $state->rememberNew( [ $finding ] ) );
	}

	public function testClassifyExcludesIgnoredFindingFromActiveAndNewActive() :void {
		$state = new CloakedPluginState();
		$finding = $this->finding( 'cloaked/cloaked.php' );
		$this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] = [
			$finding->identityKey(),
			'not-a-valid-identity',
		];

		$result = $state->classify( [ $finding ] );

		$this->assertSame( [], $result[ 'active' ] );
		$this->assertSame( [ $finding ], $result[ 'ignored' ] );
		$this->assertSame( [], $result[ 'new_active' ] );
		$this->assertSame(
			[ $finding->identityKey() ],
			$this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ]
		);
		$this->assertCount( 1, $this->opts->values[ CloakedPluginState::OPT_KEY ] );
	}

	public function testClassifyAutoIgnoresGeneratedShieldMuLoader() :void {
		$state = new CloakedPluginState();
		$this->opts->values[ 'enable_mu' ] = 'Y';
		$path = $this->writeShieldMuLoader();
		$finding = $this->shieldMuFinding( $path );

		$result = $state->classify( [ $finding ] );

		$this->assertTrue( $state->isAutoIgnored( $finding ) );
		$this->assertSame( [], $result[ 'active' ] );
		$this->assertSame( [ $finding ], $result[ 'ignored' ] );
		$this->assertSame( [], $result[ 'new_active' ] );
		$this->assertSame( [], $this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] );
		$this->assertSame( [], $this->opts->values[ CloakedPluginState::OPT_KEY ] );
	}

	public function testClassifyPrunesStaleIgnoreForGeneratedShieldMuLoader() :void {
		$state = new CloakedPluginState();
		$this->opts->values[ 'enable_mu' ] = 'Y';
		$finding = $this->shieldMuFinding( $this->writeShieldMuLoader() );
		$this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] = [ $finding->identityKey() ];

		$result = $state->classify( [ $finding ] );

		$this->assertSame( [], $result[ 'active' ] );
		$this->assertSame( [ $finding ], $result[ 'ignored' ] );
		$this->assertSame( [], $result[ 'new_active' ] );
		$this->assertSame( [], $this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] );
		$this->assertSame( [], $this->opts->values[ CloakedPluginState::OPT_KEY ] );
	}

	public function testAutoIgnoredGeneratedShieldMuLoaderCannotBeManuallyIgnoredOrUnignored() :void {
		$state = new CloakedPluginState();
		$this->opts->values[ 'enable_mu' ] = 'Y';
		$path = $this->writeShieldMuLoader();
		$finding = $this->shieldMuFinding( $path );

		$this->assertFalse( $state->ignoreIdentity( $finding->identityKey(), [ $finding ] ) );
		$this->assertFalse( $state->unignoreIdentity( $finding->identityKey(), [ $finding ] ) );
		$this->assertSame( [], $this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] );
	}

	public function testUnignorePrunesStaleShieldMuLoaderIgnore() :void {
		$state = new CloakedPluginState();
		$this->opts->values[ 'enable_mu' ] = 'Y';
		$finding = $this->shieldMuFinding( $this->writeShieldMuLoader() );
		$this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] = [ $finding->identityKey() ];

		$this->assertTrue( $state->unignoreIdentity( $finding->identityKey(), [ $finding ] ) );
		$this->assertSame( [], $this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] );
	}

	public function testClassifyDoesNotAutoIgnoreGeneratedShieldMuLoaderWhenMuOptionIsOff() :void {
		$state = new CloakedPluginState();
		$this->opts->values[ 'enable_mu' ] = 'N';
		$path = $this->writeShieldMuLoader();
		$finding = $this->shieldMuFinding( $path );

		$result = $state->classify( [ $finding ] );

		$this->assertFalse( $state->isAutoIgnored( $finding ) );
		$this->assertSame( [ $finding ], $result[ 'active' ] );
		$this->assertSame( [], $result[ 'ignored' ] );
		$this->assertSame( [ $finding ], $result[ 'new_active' ] );
		$this->assertSame( [], $this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] );
	}

	public function testClassifyActivatesShieldMuLoaderWhenMuOptionOffDespiteStaleIgnore() :void {
		$state = new CloakedPluginState();
		$this->opts->values[ 'enable_mu' ] = 'N';
		$finding = $this->shieldMuFinding( $this->writeShieldMuLoader() );
		$this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] = [ $finding->identityKey() ];

		$result = $state->classify( [ $finding ] );

		$this->assertSame( [ $finding ], $result[ 'active' ] );
		$this->assertSame( [], $result[ 'ignored' ] );
		$this->assertSame( [ $finding ], $result[ 'new_active' ] );
		$this->assertSame( [], $this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] );
	}

	public function testClassifyAlertsWhenPreviouslyAutoIgnoredShieldMuLoaderIsTampered() :void {
		$state = new CloakedPluginState();
		$this->opts->values[ 'enable_mu' ] = 'Y';
		$path = $this->writeShieldMuLoader();
		$finding = $this->shieldMuFinding( $path );

		$state->classify( [ $finding ] );
		$this->assertNotFalse( \file_put_contents(
			$path,
			( new GeneratedMuLoaderContent() )->build()."\nadd_action( 'init', 'unexpected_payload' );\n"
		) );

		$result = $state->classify( [ $finding ] );

		$this->assertSame( [ $finding ], $result[ 'active' ] );
		$this->assertSame( [], $result[ 'ignored' ] );
		$this->assertSame( [ $finding ], $result[ 'new_active' ] );
	}

	public function testClassifyActivatesTamperedShieldMuLoaderDespiteStaleIgnore() :void {
		$state = new CloakedPluginState();
		$this->opts->values[ 'enable_mu' ] = 'Y';
		$finding = $this->shieldMuFinding( $this->writeShieldMuLoader( "\nadd_action( 'init', 'unexpected_payload' );\n" ) );
		$this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] = [ $finding->identityKey() ];

		$result = $state->classify( [ $finding ] );

		$this->assertSame( [ $finding ], $result[ 'active' ] );
		$this->assertSame( [], $result[ 'ignored' ] );
		$this->assertSame( [ $finding ], $result[ 'new_active' ] );
		$this->assertSame( [], $this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] );
	}

	public function testClassifyDoesNotAutoIgnoreTamperedShieldMuLoader() :void {
		$state = new CloakedPluginState();
		$this->opts->values[ 'enable_mu' ] = 'Y';
		$path = $this->writeShieldMuLoader( "\nadd_action( 'init', 'unexpected_payload' );\n" );
		$finding = $this->shieldMuFinding( $path );

		$result = $state->classify( [ $finding ] );

		$this->assertFalse( $state->isAutoIgnored( $finding ) );
		$this->assertSame( [ $finding ], $result[ 'active' ] );
		$this->assertSame( [], $result[ 'ignored' ] );
		$this->assertSame( [ $finding ], $result[ 'new_active' ] );
		$this->assertSame( [], $this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] );
	}

	public function testClassifyDoesNotAutoIgnoreShieldMuLoaderWhenUnexpected() :void {
		$state = new CloakedPluginState();
		$this->opts->values[ 'enable_mu' ] = 'Y';
		$finding = $this->shieldMuFinding( '/mu-plugins/'.MUHandler::PLUGIN_FILE_NAME );

		$result = $state->classify( [ $finding ] );

		$this->assertSame( [ $finding ], $result[ 'active' ] );
		$this->assertSame( [ $finding ], $result[ 'new_active' ] );
		$this->assertSame( [], $result[ 'ignored' ] );
	}

	public function testClassifyActivatesMissingShieldMuLoaderDespiteStaleIgnore() :void {
		$state = new CloakedPluginState();
		$this->opts->values[ 'enable_mu' ] = 'Y';
		$dir = $this->createTrackedTempDir( 'shield-missing-mu-' );
		$finding = $this->shieldMuFinding( $dir.'/'.MUHandler::PLUGIN_FILE_NAME );
		$this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] = [ $finding->identityKey() ];

		$result = $state->classify( [ $finding ] );

		$this->assertSame( [ $finding ], $result[ 'active' ] );
		$this->assertSame( [], $result[ 'ignored' ] );
		$this->assertSame( [ $finding ], $result[ 'new_active' ] );
		$this->assertSame( [], $this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] );
	}

	public function testNormalFindingsCanStillBeIgnoredAndUnignored() :void {
		$state = new CloakedPluginState();
		$standard = $this->finding( 'cloaked/cloaked.php' );
		$mustUse = new CloakedPluginFinding(
			new PluginEntry( PluginType::MustUse, 'loader.php', 'Loader', '1.0', '/mu-plugins/loader.php' ),
			[ CloakReason::ShowAdvancedPlugins ],
			true,
			false,
			123
		);

		$this->assertTrue( $state->ignoreIdentity( $standard->identityKey(), [ $standard, $mustUse ] ) );
		$this->assertTrue( $state->ignoreIdentity( $mustUse->identityKey(), [ $standard, $mustUse ] ) );
		$this->assertSame(
			[ $standard->identityKey(), $mustUse->identityKey() ],
			$this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ]
		);

		$this->assertTrue( $state->unignoreIdentity( $standard->identityKey(), [ $standard, $mustUse ] ) );
		$this->assertTrue( $state->unignoreIdentity( $mustUse->identityKey(), [ $standard, $mustUse ] ) );
		$this->assertSame( [], $this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] );
	}

	private function finding( string $file ) :CloakedPluginFinding {
		return new CloakedPluginFinding(
			new PluginEntry( PluginType::Standard, $file, 'Cloaked', '1.0', '/plugins/'.$file ),
			[ CloakReason::AllPlugins ],
			false,
			false,
			123
		);
	}

	private function writeShieldMuLoader( string $append = '' ) :string {
		$dir = $this->createTrackedTempDir( 'shield-generated-mu-' );
		$path = $dir.'/'.MUHandler::PLUGIN_FILE_NAME;
		$this->assertNotFalse( \file_put_contents(
			$path,
			( new GeneratedMuLoaderContent() )->build().$append
		) );
		return $path;
	}

	private function shieldMuFinding( string $path ) :CloakedPluginFinding {
		return new CloakedPluginFinding(
			new PluginEntry( PluginType::MustUse, MUHandler::PLUGIN_FILE_NAME, 'Shield MU', '1.0', $path ),
			[ CloakReason::ShowAdvancedPlugins ],
			true,
			false,
			123
		);
	}

	private function labels() :object {
		return (object)[
			'Name'      => 'Shield',
			'PluginURI' => 'https://example.test/shield',
			'Author'    => 'Shield',
		];
	}
}

class CloakedPluginStateOptionsStub {

	public array $values = [
		CloakedPluginState::OPT_KEY => [],
		CloakedPluginState::IGNORE_OPT_KEY => [],
	];

	public function optGet( string $key ) {
		return $this->values[ $key ] ?? [];
	}

	public function optSet( string $key, $value ) :self {
		$this->values[ $key ] = $value;
		return $this;
	}

	public function optIs( string $key, $value ) :bool {
		return ( $this->values[ $key ] ?? null ) === $value;
	}

	public function store() :self {
		return $this;
	}
}
