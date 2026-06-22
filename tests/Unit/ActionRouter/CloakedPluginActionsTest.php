<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	CloakedPluginIgnore,
	CloakedPluginUnignore
};
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CloakedPlugins\{
	CloakedPluginFinding,
	CloakedPluginState,
	CloakReason,
	PluginEntry,
	PluginType
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestUsers
};

class CloakedPluginActionsTest extends BaseUnitTest {

	private CloakedPluginActionOptionsStub $opts;
	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::mergeItems( [
			'service_wpusers' => new UnitTestUsers( 7 ),
		] );
		$this->opts = new CloakedPluginActionOptionsStub();
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_ignore_stores_current_finding_identity() :void {
		$finding = $this->finding( 'cloaked/cloaked.php' );
		$action = new CloakedPluginIgnoreActionTestDouble(
			$this->state( [ $finding ], [] ),
			[
				'finding_id' => $finding->identityKey(),
			]
		);

		$action->process();

		$this->assertTrue( (bool)( $action->response()->payload()[ 'success' ] ?? false ) );
		$this->assertSame(
			[ $finding->identityKey() ],
			$this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ]
		);
	}

	public function test_ignore_rejects_unavailable_identity_without_mutating_state() :void {
		$before = $this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ];
		$action = new CloakedPluginIgnoreActionTestDouble(
			$this->state( [], [] ),
			[
				'finding_id' => \str_repeat( 'a', 40 ),
			]
		);

		$action->process();

		$this->assertFalse( (bool)( $action->response()->payload()[ 'success' ] ?? true ) );
		$this->assertSame( CloakedPluginIgnore::ERROR_IDENTIFIER_UNAVAILABLE, $action->response()->payload()[ 'error_code' ] ?? '' );
		$this->assertSame( $before, $this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] );
	}

	public function test_ignore_requires_identity_without_mutating_state() :void {
		$before = $this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ];
		$action = new CloakedPluginIgnoreActionTestDouble(
			$this->state( [], [] ),
			[
				'finding_id' => '',
			]
		);

		$action->process();

		$this->assertFalse( (bool)( $action->response()->payload()[ 'success' ] ?? true ) );
		$this->assertSame( CloakedPluginIgnore::ERROR_MISSING_IDENTIFIER, $action->response()->payload()[ 'error_code' ] ?? '' );
		$this->assertSame( $before, $this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] );
	}

	public function test_unignore_removes_identity_and_remains_idempotent() :void {
		$finding = $this->finding( 'cloaked/cloaked.php' );
		$this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] = [ $finding->identityKey() ];
		$action = new CloakedPluginUnignoreActionTestDouble(
			$this->state( [], [ $finding ] ),
			[
				'finding_id' => $finding->identityKey(),
			]
		);

		$action->process();
		$action->process();

		$this->assertTrue( (bool)( $action->response()->payload()[ 'success' ] ?? false ) );
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

	private function state( array $active, array $ignored ) :array {
		return [
			'all'               => \array_merge( $active, $ignored ),
			'active'            => $active,
			'ignored'           => $ignored,
			'system_suppressed' => [],
			'new_active'        => [],
		];
	}

	private function installControllerStub() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->opts = $this->opts;
		$controller->this_req = (object)[
			'request_bypasses_all_restrictions' => false,
			'is_ip_blocked'                     => false,
			'wp_is_ajax'                        => false,
			'is_security_admin'                 => false,
		];
		PluginControllerInstaller::install( $controller );
	}
}

class CloakedPluginIgnoreActionTestDouble extends CloakedPluginIgnore {

	private array $state;

	public function __construct( array $state, array $data ) {
		parent::__construct( $data );
		$this->state = $state;
	}

	protected function getMinimumUserAuthCapability() :string {
		return '';
	}

	protected function isNonceVerifyRequired() :bool {
		return false;
	}

	protected function currentFindingState() :array {
		return $this->state;
	}
}

class CloakedPluginUnignoreActionTestDouble extends CloakedPluginUnignore {

	private array $state;

	public function __construct( array $state, array $data ) {
		parent::__construct( $data );
		$this->state = $state;
	}

	protected function getMinimumUserAuthCapability() :string {
		return '';
	}

	protected function isNonceVerifyRequired() :bool {
		return false;
	}

	protected function currentFindingState() :array {
		return $this->state;
	}
}

class CloakedPluginActionOptionsStub {

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
