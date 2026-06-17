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
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	UnitTestControllerFactory
};

class CloakedPluginStateTest extends BaseUnitTest {

	private CloakedPluginStateOptionsStub $opts;

	protected function setUp() :void {
		parent::setUp();
		$this->opts = new CloakedPluginStateOptionsStub();
		UnitTestControllerFactory::install( null, null, (object)[
			'opts' => $this->opts,
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
