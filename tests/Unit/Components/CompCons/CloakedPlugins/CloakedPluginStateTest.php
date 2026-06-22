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
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\MU\MUHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	UnitTestControllerFactory
};

class CloakedPluginStateTest extends BaseUnitTest {

	private CloakedPluginStateOptionsStub $opts;
	private CloakedPluginStateMuStub $mu;

	protected function setUp() :void {
		parent::setUp();
		$this->opts = new CloakedPluginStateOptionsStub();
		$this->mu = new CloakedPluginStateMuStub();
		UnitTestControllerFactory::install( null, null, (object)[
			'opts' => $this->opts,
			'comps' => (object)[
				'mu' => $this->mu,
			],
		] );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
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

	public function testClassifySuppressesExpectedShieldMuLoader() :void {
		$state = new CloakedPluginState();
		$finding = new CloakedPluginFinding(
			new PluginEntry( PluginType::MustUse, MUHandler::PLUGIN_FILE_NAME, 'Shield MU', '1.0', '/mu-plugins/'.MUHandler::PLUGIN_FILE_NAME ),
			[ CloakReason::ShowAdvancedPlugins ],
			true,
			false,
			123
		);
		$this->mu->generatedMuLoaders[ $finding->entry->file.'|'.$finding->entry->path ] = true;

		$result = $state->classify( [ $finding ] );

		$this->assertSame( [], $result[ 'active' ] );
		$this->assertSame( [], $result[ 'ignored' ] );
		$this->assertSame( [ $finding ], $result[ 'system_suppressed' ] );
		$this->assertSame( [], $result[ 'new_active' ] );
		$this->assertSame( [], $this->opts->values[ CloakedPluginState::OPT_KEY ] );
	}

	public function testClassifyDoesNotSuppressShieldMuLoaderWhenUnexpected() :void {
		$state = new CloakedPluginState();
		$finding = new CloakedPluginFinding(
			new PluginEntry( PluginType::MustUse, MUHandler::PLUGIN_FILE_NAME, 'Shield MU', '1.0', '/mu-plugins/'.MUHandler::PLUGIN_FILE_NAME ),
			[ CloakReason::ShowAdvancedPlugins ],
			true,
			false,
			123
		);

		$result = $state->classify( [ $finding ] );

		$this->assertSame( [ $finding ], $result[ 'active' ] );
		$this->assertSame( [ $finding ], $result[ 'new_active' ] );
		$this->assertSame( [], $result[ 'system_suppressed' ] );
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

	public function store() :self {
		return $this;
	}
}

class CloakedPluginStateMuStub {

	public array $generatedMuLoaders = [];

	public function isGeneratedMuLoader( string $file, string $path ) :bool {
		return (bool)( $this->generatedMuLoaders[ $file.'|'.$path ] ?? false );
	}
}
