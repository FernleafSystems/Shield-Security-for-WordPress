<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\InstantAlerts;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CloakedPlugins\{
	CloakedPluginFinding,
	CloakedPluginState,
	PluginType
};
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\InstantAlerts\Handlers\AlertHandlerCloakedPlugins;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	UnitTestControllerFactory
};

class AlertHandlerCloakedPluginsTest extends BaseUnitTest {

	private object $opts;

	protected function setUp() :void {
		parent::setUp();
		$this->opts = new class {

			public array $values = [];

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
		};
		UnitTestControllerFactory::install( null, null, (object)[ 'opts' => $this->opts ] );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_a_plugin_ignored_while_its_alert_was_held_is_not_sent() :void {
		$ignored = [ 'type' => PluginType::Standard, 'file' => 'ignored/ignored.php', 'name' => 'Ignored' ];
		$stillCloaked = [ 'type' => PluginType::Standard, 'file' => 'still/still.php', 'name' => 'Still' ];
		$this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] = [
			CloakedPluginFinding::IdentityFor( PluginType::Standard, 'ignored/ignored.php' ),
		];

		$this->assertSame(
			[ 'hidden_plugins' => [ $stillCloaked ] ],
			( new AlertHandlerCloakedPlugins() )->alertDataStillToSend( [ 'hidden_plugins' => [ $ignored, $stillCloaked ] ] )
		);
		$this->assertSame(
			[],
			( new AlertHandlerCloakedPlugins() )->alertDataStillToSend( [ 'hidden_plugins' => [ $ignored ] ] )
		);
	}

	public function test_a_new_finding_supersedes_an_older_held_alert_for_the_same_plugin() :void {
		$earlier = [ 'type' => PluginType::Standard, 'file' => 'suite/main.php', 'version' => '1.0' ];
		$latest = [ 'type' => PluginType::Standard, 'file' => 'suite/main.php', 'version' => '2.0' ];
		$another = [ 'type' => PluginType::MustUse, 'file' => 'suite/main.php', 'version' => '1.0' ];

		$this->assertSame(
			[ 'hidden_plugins' => [ $latest, $another ] ],
			( new AlertHandlerCloakedPlugins() )->alertDataStillToSend( [ 'hidden_plugins' => [ $earlier, $another, $latest ] ] )
		);
	}
}
