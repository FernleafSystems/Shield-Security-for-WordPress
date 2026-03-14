<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\Mcp\Integration;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Abilities\AbilityDefinitions;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Integration\Wp700Integration;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Support\Compatibility;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Support\QuerySurfaceAccessPolicy;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Transport\{
	McpTransportInterface,
	NullTransport
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	McpTestControllerFactory,
	PluginControllerInstaller
};

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
				'name' => AbilityDefinitions::NAME_POSTURE_OVERVIEW,
				'args' => [ 'label' => 'overview' ],
			],
			[
				'name' => AbilityDefinitions::NAME_POSTURE_ATTENTION,
				'args' => [ 'label' => 'attention' ],
			],
			[
				'name' => AbilityDefinitions::NAME_ACTIVITY_RECENT,
				'args' => [ 'label' => 'recent' ],
			],
			[
				'name' => AbilityDefinitions::NAME_SCAN_FINDINGS,
				'args' => [ 'label' => 'findings' ],
			],
		] );

		$integration = new TestWp700Integration(
			new FixedCompatibility( true, false ),
			new FixedIntegrationAccessPolicy( true ),
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
				'abilities' => AbilityDefinitions::MCP_ABILITY_NAMES,
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
			new FixedIntegrationAccessPolicy( true ),
			$transport
		);
		$integration->register();

		$this->assertSame( [], $hooks );
		$this->assertSame( [], $transport->registeredServers );
	}

	public function test_register_returns_early_when_site_exposure_is_not_ready() :void {
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
			new FixedCompatibility( true, false ),
			new FixedIntegrationAccessPolicy( false ),
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

		$integration = new TestWp700Integration( new FixedCompatibility( true, false ), new FixedIntegrationAccessPolicy( true ) );

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

		$integration = new TestWp700Integration( new FixedCompatibility( true, false ), new FixedIntegrationAccessPolicy( true ) );
		$integration->registerAbilityCategory();

		$this->assertSame( [], $registered );
	}

	public function test_register_abilities_registers_expected_names_and_skips_existing_ones() :void {
		$registered = [];
		$this->installController( [
			[
				'name' => AbilityDefinitions::NAME_POSTURE_OVERVIEW,
				'args' => [ 'label' => 'overview' ],
			],
			[
				'name' => AbilityDefinitions::NAME_POSTURE_ATTENTION,
				'args' => [ 'label' => 'attention' ],
			],
			[
				'name' => AbilityDefinitions::NAME_ACTIVITY_RECENT,
				'args' => [ 'label' => 'recent' ],
			],
			[
				'name' => AbilityDefinitions::NAME_SCAN_FINDINGS,
				'args' => [ 'label' => 'findings' ],
			],
		] );

		Functions\when( 'wp_has_ability' )->alias(
			static fn( string $name ) :bool => $name === AbilityDefinitions::NAME_POSTURE_OVERVIEW
		);
		Functions\when( 'wp_register_ability' )->alias(
			static function ( string $name, array $args ) use ( &$registered ) :bool {
				$registered[] = [ $name, $args ];
				return true;
			}
		);

		$integration = new TestWp700Integration( new FixedCompatibility( true, false ), new FixedIntegrationAccessPolicy( true ) );
		$integration->registerAbilities();

		$this->assertSame( [
			AbilityDefinitions::NAME_POSTURE_ATTENTION,
			AbilityDefinitions::NAME_ACTIVITY_RECENT,
			AbilityDefinitions::NAME_SCAN_FINDINGS,
		], \array_column( $registered, 0 ) );
	}

	public function test_get_transport_returns_null_transport_when_adapter_is_not_supported() :void {
		$integration = new TestWp700Integration( new FixedCompatibility( true, false ), new FixedIntegrationAccessPolicy( true ) );

		$this->assertInstanceOf( NullTransport::class, $integration->getTransport() );
	}

	private function installController( array $abilityDefinitions = [] ) :void {
		McpTestControllerFactory::install( [
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
						'abilities' => AbilityDefinitions::MCP_ABILITY_NAMES,
					];
				}
			},
		] );
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

	private QuerySurfaceAccessPolicy $accessPolicy;

	private ?McpTransportInterface $transportOverride;

	public function __construct(
		Compatibility $compatibility,
		QuerySurfaceAccessPolicy $accessPolicy,
		?McpTransportInterface $transportOverride = null
	) {
		$this->compatibility = $compatibility;
		$this->accessPolicy = $accessPolicy;
		$this->transportOverride = $transportOverride;
	}

	public function getTransport() :McpTransportInterface {
		return $this->transportOverride ?? parent::getTransport();
	}

	protected function getCompatibility() :Compatibility {
		return $this->compatibility;
	}

	protected function getAccessPolicy() :QuerySurfaceAccessPolicy {
		return $this->accessPolicy;
	}
}

class FixedIntegrationAccessPolicy extends QuerySurfaceAccessPolicy {

	private bool $ready;

	public function __construct( bool $ready ) {
		$this->ready = $ready;
	}

	public function isSiteExposureReady() :bool {
		return $this->ready;
	}
}
