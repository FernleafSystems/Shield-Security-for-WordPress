<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\UserManagement\Lib\Suspend;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Suspend\UserSuspendController;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	InvokesNonPublicMethods,
	PluginControllerInstaller,
	ServicesState,
	UnitTestRequest
};

class UserSuspendControllerTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	private array $servicesSnapshot = [];

	/** @var array<string,callable> */
	private array $filters = [];

	protected function setUp() :void {
		parent::setUp();

		if ( !\defined( 'ARRAY_A' ) ) {
			\define( 'ARRAY_A', 'ARRAY_A' );
		}

		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'add_filter' )->alias( function ( string $tag, callable $callback ) :bool {
			$this->filters[ $tag ] = $callback;
			return true;
		} );

		$this->servicesSnapshot = ServicesState::snapshot();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_add_suspended_user_filters_maps_idle_and_password_queries_correctly() :void {
		$userMeta = new UserSuspendControllerUserMetaSelectorProvider();

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->comps = (object)[
			'opts_lookup' => new class {
				public function getPassExpireTimeout() :int {
					return 900;
				}
			},
		];
		$controller->db_con = (object)[
			'user_meta' => $userMeta,
		];

		PluginControllerInstaller::install( $controller );

		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [], '127.0.0.1', 1700000000 ),
		] );

		$this->invokeNonPublicMethod( new UserSuspendControllerForTest(), 'addSuspendedUserFilters' );

		$queryFilter = $this->filters[ 'users_list_table_query_args' ] ?? null;
		$this->assertIsCallable( $queryFilter );

		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [ 'shield_users_idle' => 1 ], '127.0.0.1', 1700000000 ),
		] );

		$idleArgs = $queryFilter( [] );

		$this->assertSame( [ 101 ], $idleArgs[ 'include' ] ?? [] );
		$this->assertCount( 2, $userMeta->selectors );
		$this->assertSame( [ [ 'idle', 1699999400 ] ], $userMeta->selectors[ 1 ]->filterCalls );

		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [ 'shield_users_pass' => 1 ], '127.0.0.1', 1700000000 ),
		] );

		$passwordArgs = $queryFilter( [] );

		$this->assertSame( [ 202 ], $passwordArgs[ 'include' ] ?? [] );
		$this->assertCount( 3, $userMeta->selectors );
		$this->assertSame( [ [ 'pass', 1699999100 ] ], $userMeta->selectors[ 2 ]->filterCalls );
	}
}

class UserSuspendControllerForTest extends UserSuspendController {

	public function isSuspendManualEnabled() :bool {
		return false;
	}

	public function isSuspendAutoIdleEnabled() :bool {
		return true;
	}

	public function getSuspendAutoIdleTime() :int {
		return 600;
	}

	public function isSuspendAutoPasswordEnabled() :bool {
		return true;
	}
}

class UserSuspendControllerUserMetaSelectorProvider {

	/** @var UserSuspendControllerFakeSelector[] */
	public array $selectors = [];

	public function getQuerySelector() :UserSuspendControllerFakeSelector {
		$selector = new UserSuspendControllerFakeSelector();
		$this->selectors[] = $selector;
		return $selector;
	}
}

class UserSuspendControllerFakeSelector {

	/** @var array<int,array{0:string,1:int}> */
	public array $filterCalls = [];

	private string $activeFilter = '';

	public function reset() :self {
		$this->activeFilter = '';
		return $this;
	}

	public function filterByHardSuspended() :self {
		$this->activeFilter = 'manual';
		return $this;
	}

	public function filterByIdle( int $expiresAt ) :self {
		$this->activeFilter = 'idle';
		$this->filterCalls[] = [ 'idle', $expiresAt ];
		return $this;
	}

	public function filterByPassExpired( int $expiresAt ) :self {
		$this->activeFilter = 'pass';
		$this->filterCalls[] = [ 'pass', $expiresAt ];
		return $this;
	}

	public function count() :int {
		return match ( $this->activeFilter ) {
			'idle', 'pass' => 1,
			default => 0,
		};
	}

	public function setResultsAsVo( bool $asVO ) :self {
		return $this;
	}

	public function setSelectResultsFormat( string $format ) :self {
		return $this;
	}

	public function setColumnsToSelect( array $columns ) :self {
		return $this;
	}

	public function queryWithResult() :array {
		return match ( $this->activeFilter ) {
			'idle' => [ [ 'user_id' => 101 ] ],
			'pass' => [ [ 'user_id' => 202 ] ],
			default => [],
		};
	}
}
