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
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\MU\MUHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestUsers
};

class CloakedPluginActionsTest extends BaseUnitTest {

	use TempDirLifecycleTrait;

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
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function test_ignore_defaults_to_until_changed_with_the_reviewed_contents() :void {
		$finding = $this->findingOnDisk( 'cloaked/cloaked.php' );
		$action = new CloakedPluginIgnoreActionTestDouble(
			$this->state( [ $finding ], [] ),
			[
				'identifier'    => $finding->identityKey(),
				'contents_hash' => $finding->contentsHash(),
			]
		);

		$action->process();

		$this->assertTrue( (bool)( $action->response()->payload()[ 'success' ] ?? false ) );
		$record = $this->opts->values[ CloakedPluginState::IGNORE_MODES_OPT_KEY ][ $finding->identityKey() ];
		$this->assertSame( CloakedPluginState::IGNORE_MODE_CONTENTS, $record[ 'mode' ] );
		$this->assertSame( $finding->contentsHash(), $record[ 'contents_hash' ] );
	}

	public function test_ignore_refuses_contents_changed_since_they_were_reviewed() :void {
		$finding = $this->findingOnDisk( 'cloaked/cloaked.php' );
		$reviewed = $finding->contentsHash();
		$this->assertNotFalse( \file_put_contents( $finding->entry->path, "<?php\nrun_payload();\n" ) );
		$action = new CloakedPluginIgnoreActionTestDouble(
			$this->state( [ $finding ], [] ),
			[
				'identifier'    => $finding->identityKey(),
				'ignore_mode'   => CloakedPluginState::IGNORE_MODE_CONTENTS,
				'contents_hash' => $reviewed,
			]
		);

		$action->process();

		$this->assertFalse( (bool)( $action->response()->payload()[ 'success' ] ?? true ) );
		$this->assertSame( CloakedPluginIgnore::ERROR_IDENTIFIER_UNAVAILABLE, $action->response()->payload()[ 'error_code' ] ?? '' );
		$this->assertSame( [], $this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] );
	}

	public function test_always_ignore_stores_the_plugin_whatever_it_contains() :void {
		$finding = $this->finding( 'cloaked/cloaked.php' );
		$action = new CloakedPluginIgnoreActionTestDouble(
			$this->state( [ $finding ], [] ),
			[
				'identifier'  => $finding->identityKey(),
				'ignore_mode' => CloakedPluginState::IGNORE_MODE_PATH,
			]
		);

		$action->process();

		$this->assertTrue( (bool)( $action->response()->payload()[ 'success' ] ?? false ) );
		$record = $this->opts->values[ CloakedPluginState::IGNORE_MODES_OPT_KEY ][ $finding->identityKey() ];
		$this->assertSame( CloakedPluginState::IGNORE_MODE_PATH, $record[ 'mode' ] );
		$this->assertSame( '', $record[ 'contents_hash' ] );
	}

	public function test_ignore_all_stores_every_identity_with_its_own_contents_in_one_request() :void {
		$first = $this->findingOnDisk( 'first/first.php', "<?php\nfirst();\n" );
		$second = $this->findingOnDisk( 'second/second.php', "<?php\nsecond();\n" );
		$action = new CloakedPluginIgnoreActionTestDouble(
			$this->state( [ $first, $second ], [] ),
			[
				'identifiers'     => [ $first->identityKey(), $second->identityKey() ],
				'ignore_mode'     => CloakedPluginState::IGNORE_MODE_CONTENTS,
				'contents_hashes' => [
					$first->identityKey()  => $first->contentsHash(),
					$second->identityKey() => $second->contentsHash(),
				],
			]
		);

		$action->process();

		$this->assertTrue( $action->response()->payload()[ 'success' ] );
		$records = $this->opts->values[ CloakedPluginState::IGNORE_MODES_OPT_KEY ];
		$this->assertSame( $first->contentsHash(), $records[ $first->identityKey() ][ 'contents_hash' ] );
		$this->assertSame( $second->contentsHash(), $records[ $second->identityKey() ][ 'contents_hash' ] );
	}

	public function test_ignore_all_still_applies_when_one_plugin_has_gone() :void {
		$missing = $this->findingOnDisk( 'missing/missing.php' );
		$present = $this->findingOnDisk( 'present/present.php' );
		$missingHash = $missing->contentsHash();
		$this->assertTrue( \unlink( $missing->entry->path ) );
		$action = new CloakedPluginIgnoreActionTestDouble(
			$this->state( [ $present ], [] ),
			[
				'identifiers'     => [ $missing->identityKey(), $present->identityKey() ],
				'ignore_mode'     => CloakedPluginState::IGNORE_MODE_CONTENTS,
				'contents_hashes' => [
					$missing->identityKey() => $missingHash,
					$present->identityKey() => $present->contentsHash(),
				],
			]
		);

		$action->process();

		$this->assertTrue( $action->response()->payload()[ 'success' ] );
		$this->assertSame( [ $present->identityKey() ], $this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] );
	}

	public function test_ignore_all_takes_each_plugin_only_on_its_own_reviewed_contents() :void {
		// Identical files, so one plugin's hash would pass for the other's if it were allowed to stand in.
		$reviewed = $this->findingOnDisk( 'reviewed/reviewed.php' );
		$unreviewed = $this->findingOnDisk( 'unreviewed/unreviewed.php' );
		$action = new CloakedPluginIgnoreActionTestDouble(
			$this->state( [ $reviewed, $unreviewed ], [] ),
			[
				'identifiers'     => [ $reviewed->identityKey(), $unreviewed->identityKey() ],
				'ignore_mode'     => CloakedPluginState::IGNORE_MODE_CONTENTS,
				'contents_hash'   => $reviewed->contentsHash(),
				'contents_hashes' => [ $reviewed->identityKey() => $reviewed->contentsHash() ],
			]
		);

		$action->process();

		$this->assertTrue( $action->response()->payload()[ 'success' ] );
		$this->assertSame( [ $reviewed->identityKey() ], $this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] );
	}

	public function test_stop_ignoring_all_removes_every_identity_in_one_request() :void {
		$first = $this->finding( 'first/first.php' );
		$second = $this->finding( 'second/second.php' );
		$this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] = [ $first->identityKey(), $second->identityKey() ];
		$action = new CloakedPluginUnignoreActionTestDouble(
			$this->state( [], [ $first, $second ] ),
			[
				'identifiers' => [ $first->identityKey(), $second->identityKey() ],
			]
		);

		$action->process();

		$this->assertTrue( $action->response()->payload()[ 'success' ] );
		$this->assertSame( [], $this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] );
	}

	public function test_ignore_rejects_shield_mu_loader_identity_without_mutating_state() :void {
		$finding = new CloakedPluginFinding(
			new PluginEntry( PluginType::MustUse, MUHandler::PLUGIN_FILE_NAME, 'Shield MU', '1.0', '/mu-plugins/'.MUHandler::PLUGIN_FILE_NAME ),
			[ CloakReason::ShowAdvancedPlugins ],
			true,
			false,
			123
		);
		$action = new CloakedPluginIgnoreActionTestDouble(
			$this->state( [ $finding ], [] ),
			[
				'identifier'  => $finding->identityKey(),
				'ignore_mode' => CloakedPluginState::IGNORE_MODE_PATH,
			]
		);

		$action->process();

		$this->assertFalse( (bool)( $action->response()->payload()[ 'success' ] ?? true ) );
		$this->assertSame( CloakedPluginIgnore::ERROR_IDENTIFIER_UNAVAILABLE, $action->response()->payload()[ 'error_code' ] ?? '' );
		$this->assertSame( [], $this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ] );
	}

	public function test_ignore_rejects_unavailable_identity_without_mutating_state() :void {
		$before = $this->opts->values[ CloakedPluginState::IGNORE_OPT_KEY ];
		$action = new CloakedPluginIgnoreActionTestDouble(
			$this->state( [], [] ),
			[
				'identifier'  => \str_repeat( 'a', 40 ),
				'ignore_mode' => CloakedPluginState::IGNORE_MODE_PATH,
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
				'identifier' => '',
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
				'identifier' => $finding->identityKey(),
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

	private function findingOnDisk( string $file, string $contents = "<?php\nrun();\n" ) :CloakedPluginFinding {
		$path = $this->createTrackedTempDir( 'shield-cloaked-action-' ).'/'.\basename( $file );
		$this->assertNotFalse( \file_put_contents( $path, $contents ) );
		return new CloakedPluginFinding(
			new PluginEntry( PluginType::Standard, $file, 'Cloaked', '1.0', $path ),
			[ CloakReason::AllPlugins ],
			false,
			false,
			123
		);
	}

	private function state( array $active, array $ignored ) :array {
		return [
			'all'        => \array_merge( $active, $ignored ),
			'active'     => $active,
			'ignored'    => $ignored,
			'new_active' => [],
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
