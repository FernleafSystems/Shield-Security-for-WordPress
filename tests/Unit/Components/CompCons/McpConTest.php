<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Abilities\AbilityDefinitions;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Support\QuerySurfaceAccessPolicy;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\McpCon;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	McpTestControllerFactory,
	PluginControllerInstaller
};
use FernleafSystems\Wordpress\Services\Utilities\Mcp\{
	AbilitiesApiInterface,
	ServerRegistrar
};
use FernleafSystems\Wordpress\Services\Utilities\Mcp\Support\{
	Compatibility,
	RuntimeRegistry
};
use FernleafSystems\Wordpress\Services\Utilities\Mcp\Transport\McpTransportInterface;

class McpConTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->returnArg();

		RuntimeRegistry::Reset();
		McpTestControllerFactory::install( [
			'events'     => new class {
				public function fireEvent( string $event, array $meta = [] ) :void {
					unset( $event, $meta );
				}
			},
			'scans'      => new class {
				public function getScanSlugs() :array {
					return [ 'afs', 'wpv', 'apc' ];
				}
			},
			'site_query' => new class {
				public function overview() :array {
					return [];
				}

				public function attention() :array {
					return [];
				}

				public function recentActivity() :array {
					return [];
				}

				public function scanFindings( array $scanSlugs = [], array $statesToInclude = [] ) :array {
					unset( $scanSlugs, $statesToInclude );
					return [];
				}
			},
		] );
	}

	protected function tearDown() :void {
		RuntimeRegistry::Reset();
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_build_registrar_registers_expected_server_category_and_abilities() :void {
		$api = new CapturingAbilitiesApi();
		$transport = new CapturingTransport();
		$mcp = new RegistrarBuildingMcpCon( new FixedAccessPolicy( true ), $api, $transport, new FixedCompatibility( true ) );

		$registrar = $mcp->exposeRegistrar();
		$this->assertTrue( $registrar->isAvailable() );

		$registrar->register();

		$this->assertSame( [
			[
				'server_id' => McpCon::SERVER_ID,
				'namespace' => McpCon::ROUTE_NAMESPACE,
				'route'     => McpCon::ROUTE_SEGMENT,
				'version'   => '1.2.3',
				'abilities' => AbilityDefinitions::MCP_ABILITY_NAMES,
			],
		], $transport->registeredServers );
		$this->assertCount( 1, $api->categoryHooks );
		$this->assertCount( 1, $api->abilityHooks );

		$api->categoryHooks[ 0 ]();
		$api->abilityHooks[ 0 ]();

		$this->assertSame( [
			[
				'slug' => AbilityDefinitions::CATEGORY_SLUG,
				'args' => [
					'label'       => 'Shield Security',
					'description' => 'Read-only security posture and activity abilities for Shield Security.',
				],
			],
		], $api->registeredCategories );
		$this->assertSame( AbilityDefinitions::MCP_ABILITY_NAMES, \array_column( $api->registeredAbilities, 'name' ) );
	}

	public function test_build_registrar_availability_uses_site_exposure_readiness() :void {
		$api = new CapturingAbilitiesApi();
		$transport = new CapturingTransport();
		$mcp = new RegistrarBuildingMcpCon( new FixedAccessPolicy( false ), $api, $transport, new FixedCompatibility( true ) );

		$registrar = $mcp->exposeRegistrar();

		$this->assertFalse( $registrar->isAvailable() );

		$registrar->register();

		$this->assertSame( [], $api->categoryHooks );
		$this->assertSame( [], $api->abilityHooks );
		$this->assertSame( [], $transport->registeredServers );
	}

	public function test_execute_builds_registrar_once_and_registers_once() :void {
		$registrar = new FakeRegistrar( true, new CapturingTransport() );
		$mcp = new FakeRegistrarMcpCon( $registrar );

		$mcp->execute();
		$mcp->execute();

		$this->assertSame( 1, $mcp->buildCalls );
		$this->assertSame( 1, $registrar->registerCalls );
	}

	public function test_is_available_delegates_to_registrar() :void {
		$mcp = new FakeRegistrarMcpCon( new FakeRegistrar( false, new CapturingTransport() ) );

		$this->assertFalse( $mcp->isAvailable() );
	}

	public function test_get_transport_returns_registrar_transport() :void {
		$transport = new CapturingTransport();
		$mcp = new FakeRegistrarMcpCon( new FakeRegistrar( true, $transport ) );

		$this->assertSame( $transport, $mcp->getTransport() );
	}

	public function test_is_transport_available_requires_supported_available_transport() :void {
		$this->assertFalse(
			( new FakeRegistrarMcpCon( new FakeRegistrar( false, new CapturingTransport( true ) ) ) )->isTransportAvailable()
		);
		$this->assertFalse(
			( new FakeRegistrarMcpCon( new FakeRegistrar( true, new CapturingTransport( false ) ) ) )->isTransportAvailable()
		);
		$this->assertTrue(
			( new FakeRegistrarMcpCon( new FakeRegistrar( true, new CapturingTransport( true ) ) ) )->isTransportAvailable()
		);
	}
}

class RegistrarBuildingMcpCon extends McpCon {

	private QuerySurfaceAccessPolicy $policy;

	private ?AbilitiesApiInterface $api;

	private ?McpTransportInterface $transport;

	private ?Compatibility $compatibility;

	public function __construct(
		QuerySurfaceAccessPolicy $policy,
		?AbilitiesApiInterface $api = null,
		?McpTransportInterface $transport = null,
		?Compatibility $compatibility = null
	) {
		$this->policy = $policy;
		$this->api = $api;
		$this->transport = $transport;
		$this->compatibility = $compatibility;
	}

	public function exposeRegistrar() :ServerRegistrar {
		return $this->getRegistrar();
	}

	protected function buildRegistrar() :ServerRegistrar {
		$registrar = parent::buildRegistrar();

		if ( $this->api !== null ) {
			$registrar->setAbilitiesApi( $this->api );
		}
		if ( $this->transport !== null ) {
			$registrar->setTransport( $this->transport );
		}
		if ( $this->compatibility !== null ) {
			$registrar->setCompatibility( $this->compatibility );
		}

		return $registrar;
	}

	protected function getAccessPolicy() :QuerySurfaceAccessPolicy {
		return $this->policy;
	}
}

class FakeRegistrarMcpCon extends McpCon {

	public int $buildCalls = 0;

	private ServerRegistrar $registrar;

	public function __construct( ServerRegistrar $registrar ) {
		$this->registrar = $registrar;
	}

	protected function buildRegistrar() :ServerRegistrar {
		$this->buildCalls++;
		return $this->registrar;
	}
}

class FixedAccessPolicy extends QuerySurfaceAccessPolicy {

	private bool $ready;

	public function __construct( bool $ready ) {
		$this->ready = $ready;
	}

	public function isSiteExposureReady() :bool {
		return $this->ready;
	}
}

class FixedCompatibility extends Compatibility {

	private bool $supported;

	public function __construct( bool $supported ) {
		$this->supported = $supported;
	}

	public function supportsAbilitiesIntegration() :bool {
		return $this->supported;
	}

	public function supportsAdapterTransport() :bool {
		return $this->supported;
	}
}

class CapturingAbilitiesApi implements AbilitiesApiInterface {

	/** @var callable[] */
	public array $categoryHooks = [];

	/** @var callable[] */
	public array $abilityHooks = [];

	/** @var array<int,array{slug:string,args:array<string,mixed>}> */
	public array $registeredCategories = [];

	/** @var array<int,array{name:string,args:array<string,mixed>}> */
	public array $registeredAbilities = [];

	public function addCategoryRegistrationHook( callable $callback ) :void {
		$this->categoryHooks[] = $callback;
	}

	public function addAbilityRegistrationHook( callable $callback ) :void {
		$this->abilityHooks[] = $callback;
	}

	public function hasCategory( string $slug ) :bool {
		unset( $slug );
		return false;
	}

	public function registerCategory( string $slug, array $args ) :void {
		$this->registeredCategories[] = [
			'slug' => $slug,
			'args' => $args,
		];
	}

	public function hasAbility( string $name ) :bool {
		unset( $name );
		return false;
	}

	public function registerAbility( string $name, array $args ) :void {
		$this->registeredAbilities[] = [
			'name' => $name,
			'args' => $args,
		];
	}
}

class CapturingTransport implements McpTransportInterface {

	/** @var array<int,array{server_id:string,namespace:string,route:string,version:string,abilities:string[]}> */
	public array $registeredServers = [];

	private bool $supported;

	public function __construct( bool $supported = true ) {
		$this->supported = $supported;
	}

	public function isSupported() :bool {
		return $this->supported;
	}

	public function registerServer( array $serverDefinition ) :void {
		$this->registeredServers[] = $serverDefinition;
	}

	public function getIdentifier() :string {
		return 'capturing_transport';
	}
}

class FakeRegistrar extends ServerRegistrar {

	public int $registerCalls = 0;

	private bool $available;

	private McpTransportInterface $transport;

	public function __construct( bool $available, McpTransportInterface $transport ) {
		$this->available = $available;
		$this->transport = $transport;
	}

	public function register() :void {
		$this->registerCalls++;
	}

	public function isAvailable() :bool {
		return $this->available;
	}

	public function getTransport() :McpTransportInterface {
		return $this->transport;
	}
}
