<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\Mcp\Integration;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Integration\Wp700Integration;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Support\Compatibility;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Transport\{
	McpTransportInterface,
	NullTransport
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;

class Wp700IntegrationTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->returnArg();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_register_hooks_abilities_and_hands_server_definition_to_transport_when_supported() :void {
		$hooks = [];
		$transport = new CapturingTransport();

		Functions\when( 'add_action' )->alias(
			static function ( string $hook, callable $callback ) use ( &$hooks ) :bool {
				$hooks[ $hook ][] = $callback;
				return true;
			}
		);

		$this->installController( [
			[
				'name' => 'shield/posture/overview/get',
				'args' => [ 'label' => 'overview' ],
			],
			[
				'name' => 'shield/posture/attention/get',
				'args' => [ 'label' => 'attention' ],
			],
			[
				'name' => 'shield/activity/recent/get',
				'args' => [ 'label' => 'recent' ],
			],
			[
				'name' => 'shield/scan/findings/get',
				'args' => [ 'label' => 'findings' ],
			],
		] );

		$integration = new TestWp700Integration(
			new FixedCompatibility( true, false ),
			$transport
		);
		$integration->register();

		$this->assertArrayHasKey( 'wp_abilities_api_categories_init', $hooks );
		$this->assertArrayHasKey( 'wp_abilities_api_init', $hooks );
		$this->assertSame( [
			[
				'server_id' => 'shield-security',
				'namespace' => 'shield-security',
				'route'     => 'mcp',
				'version'   => '1.2.3',
				'abilities' => [
					'shield/posture/overview/get',
					'shield/posture/attention/get',
					'shield/activity/recent/get',
					'shield/scan/findings/get',
				],
			],
		], $transport->registeredServers );
	}

	public function test_register_returns_early_when_integration_is_unsupported() :void {
		$hooks = [];
		$transport = new CapturingTransport();

		Functions\when( 'add_action' )->alias(
			static function ( string $hook, callable $callback ) use ( &$hooks ) :bool {
				$hooks[ $hook ][] = $callback;
				return true;
			}
		);

		$this->installController();

		$integration = new TestWp700Integration(
			new FixedCompatibility( false, false ),
			$transport
		);
		$integration->register();

		$this->assertSame( [], $hooks );
		$this->assertSame( [], $transport->registeredServers );
	}

	public function test_register_ability_category_registers_missing_category() :void {
		$registered = [];

		Functions\when( 'wp_has_ability_category' )->justReturn( false );
		Functions\when( 'wp_register_ability_category' )->alias(
			static function ( string $slug, array $args ) use ( &$registered ) :bool {
				$registered[] = [ $slug, $args ];
				return true;
			}
		);

		$integration = new TestWp700Integration( new FixedCompatibility( true, false ) );

		$integration->registerAbilityCategory();

		$this->assertSame( [
			[
				'shield-security',
				[
					'label'       => 'Shield Security',
					'description' => 'Read-only security posture and activity abilities for Shield Security.',
				],
			],
		], $registered );
	}

	public function test_register_ability_category_skips_when_category_is_already_registered() :void {
		$registered = [];

		Functions\when( 'wp_has_ability_category' )->justReturn( true );
		Functions\when( 'wp_register_ability_category' )->alias(
			static function ( string $slug, array $args ) use ( &$registered ) :bool {
				$registered[] = [ $slug, $args ];
				return true;
			}
		);

		$integration = new TestWp700Integration( new FixedCompatibility( true, false ) );
		$integration->registerAbilityCategory();

		$this->assertSame( [], $registered );
	}

	public function test_register_abilities_registers_expected_names_and_skips_existing_ones() :void {
		$registered = [];
		$this->installController( [
			[
				'name' => 'shield/posture/overview/get',
				'args' => [ 'label' => 'overview' ],
			],
			[
				'name' => 'shield/posture/attention/get',
				'args' => [ 'label' => 'attention' ],
			],
			[
				'name' => 'shield/activity/recent/get',
				'args' => [ 'label' => 'recent' ],
			],
			[
				'name' => 'shield/scan/findings/get',
				'args' => [ 'label' => 'findings' ],
			],
		] );

		Functions\when( 'wp_has_ability' )->alias(
			static fn( string $name ) :bool => $name === 'shield/posture/overview/get'
		);
		Functions\when( 'wp_register_ability' )->alias(
			static function ( string $name, array $args ) use ( &$registered ) :bool {
				$registered[] = [ $name, $args ];
				return true;
			}
		);

		$integration = new TestWp700Integration( new FixedCompatibility( true, false ) );
		$integration->registerAbilities();

		$this->assertSame( [
			'shield/posture/attention/get',
			'shield/activity/recent/get',
			'shield/scan/findings/get',
		], \array_column( $registered, 0 ) );
	}

	public function test_get_transport_returns_null_transport_when_adapter_is_not_supported() :void {
		$integration = new TestWp700Integration( new FixedCompatibility( true, false ) );

		$this->assertInstanceOf( NullTransport::class, $integration->getTransport() );
	}

	private function installController( array $abilityDefinitions = [] ) :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->cfg = new class {
			public function version() :string {
				return '1.2.3';
			}
		};
		$controller->comps = (object)[
			'mcp' => new class( $abilityDefinitions ) {
				private array $abilityDefinitions;

				public function __construct( array $abilityDefinitions ) {
					$this->abilityDefinitions = $abilityDefinitions;
				}

				public function enumAbilityDefinitions() :array {
					return $this->abilityDefinitions;
				}

				public function buildServerDefinition() :array {
					return [
						'server_id' => 'shield-security',
						'namespace' => 'shield-security',
						'route'     => 'mcp',
						'version'   => '1.2.3',
						'abilities' => [
							'shield/posture/overview/get',
							'shield/posture/attention/get',
							'shield/activity/recent/get',
							'shield/scan/findings/get',
						],
					];
				}
			},
		];

		PluginControllerInstaller::install( $controller );
	}
}

class FixedCompatibility extends Compatibility {

	private bool $abilitiesSupported;

	private bool $adapterSupported;

	public function __construct( bool $abilitiesSupported, bool $adapterSupported ) {
		$this->abilitiesSupported = $abilitiesSupported;
		$this->adapterSupported = $adapterSupported;
	}

	public function supportsAbilitiesIntegration() :bool {
		return $this->abilitiesSupported;
	}

	public function supportsAdapterTransport() :bool {
		return $this->adapterSupported;
	}
}

class CapturingTransport implements McpTransportInterface {

	public array $registeredServers = [];

	public function isSupported() :bool {
		return true;
	}

	public function registerServer( array $serverDefinition ) :void {
		$this->registeredServers[] = $serverDefinition;
	}

	public function getIdentifier() :string {
		return 'capturing_transport';
	}
}

class TestWp700Integration extends Wp700Integration {

	private Compatibility $compatibility;

	private ?McpTransportInterface $transportOverride;

	public function __construct( Compatibility $compatibility, ?McpTransportInterface $transportOverride = null ) {
		$this->compatibility = $compatibility;
		$this->transportOverride = $transportOverride;
	}

	public function getTransport() :McpTransportInterface {
		return $this->transportOverride ?? parent::getTransport();
	}

	protected function getCompatibility() :Compatibility {
		return $this->compatibility;
	}
}
