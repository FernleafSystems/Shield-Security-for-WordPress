<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\HiddenPlugins;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\HiddenPlugins\{
	HiddenPluginFinding,
	HiddenPluginState,
	HiddenReason,
	PluginEntry,
	PluginType
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	UnitTestControllerFactory
};

class HiddenPluginStateTest extends BaseUnitTest {

	private HiddenPluginStateOptionsStub $opts;

	protected function setUp() :void {
		parent::setUp();
		$this->opts = new HiddenPluginStateOptionsStub();
		UnitTestControllerFactory::install( null, null, (object)[
			'opts' => $this->opts,
		] );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function testRememberNewReturnsFindingOnlyOncePerFingerprint() :void {
		$state = new HiddenPluginState();
		$finding = $this->finding( 'hidden/hidden.php' );

		$this->assertSame( [ $finding ], $state->rememberNew( [ $finding ] ) );
		$this->assertSame( [], $state->rememberNew( [ $finding ] ) );
		$this->assertCount( 1, $this->opts->values[ HiddenPluginState::OPT_KEY ] );
	}

	public function testResolvedFindingsAreRemovedFromStateSoReappearingFindingsAlertAgain() :void {
		$state = new HiddenPluginState();
		$finding = $this->finding( 'hidden/hidden.php' );

		$state->rememberNew( [ $finding ] );
		$state->rememberNew( [] );

		$this->assertSame( [ $finding ], $state->rememberNew( [ $finding ] ) );
	}

	private function finding( string $file ) :HiddenPluginFinding {
		return new HiddenPluginFinding(
			new PluginEntry( PluginType::Standard, $file, 'Hidden', '1.0', '/plugins/'.$file ),
			[ HiddenReason::AllPlugins ],
			false,
			false,
			123
		);
	}
}

class HiddenPluginStateOptionsStub {

	public array $values = [
		HiddenPluginState::OPT_KEY => [],
	];

	public function optGet( string $key ) :mixed {
		return $this->values[ $key ] ?? [];
	}

	public function optSet( string $key, mixed $value ) :self {
		$this->values[ $key ] = $value;
		return $this;
	}

	public function store() :self {
		return $this;
	}
}
