<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Controller\Database;

use Carbon\Carbon;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Database\CleanScansDB;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestRequest
};
use FernleafSystems\Wordpress\Services\Core\Db;

class CleanScansDBTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_run_keeps_latest_completed_full_runs_and_prunes_scoped_and_failed_runs() :void {
		$queries = [];
		$deleteCalls = [];
		$selectorCalls = [];
		$threshold = 1700000000 - 86400;

		$request = new class( [], '127.0.0.1', 1700000000 ) extends UnitTestRequest {
			public function carbon( $setTimezone = false, bool $userLocale = true ) :Carbon {
				unset( $setTimezone, $userLocale );
				return Carbon::createFromTimestampUTC( 1700000000 );
			}
		};

		ServicesState::installItems( [
			'service_request' => $request,
			'service_wpdb'    => new class( $queries ) extends Db {
				public array $queries;

				public function __construct( array &$queries ) {
					$this->queries = &$queries;
				}

				public function doSql( $sql ) :bool {
					$this->queries[] = $sql;
					return true;
				}
			},
		] );

		$this->installController( $deleteCalls, $selectorCalls );

		( new CleanScansDB() )->run();

		$this->assertCount( 1, $deleteCalls );
		$this->assertSame( [
			'not_finished' => true,
			'created_at'   => $threshold,
			'operator'     => '<',
		], $deleteCalls[ 0 ] );

		$this->assertCount( 3, $queries );
		$this->assertStringContainsString( "`status`='failed'", $queries[ 0 ] );
		$this->assertStringContainsString( (string)$threshold, $queries[ 0 ] );
		$this->assertStringContainsString( "`scan`='afs'", $queries[ 1 ] );
		$this->assertStringContainsString( "`scope_type`!='full'", $queries[ 1 ] );
		$this->assertStringContainsString( "`status`='completed'", $queries[ 2 ] );
		$this->assertStringContainsString( "`scope_type`='full'", $queries[ 2 ] );
		$this->assertStringContainsString( 'NOT IN (101, 202, 303)', $queries[ 2 ] );
		$this->assertSame( 'completed', $selectorCalls[ 0 ][ 'status' ] ?? null );
		$this->assertSame( 'full', $selectorCalls[ 0 ][ 'scope_type' ] ?? null );
	}

	private function installController( array &$deleteCalls, array &$selectorCalls ) :void {
		$latestIds = [
			'afs' => 101,
			'apc' => 202,
			'wpv' => 303,
		];

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->db_con = (object)[
			'scans' => new class( $deleteCalls, $selectorCalls, $latestIds ) {
				private array $deleteCalls;
				private array $selectorCalls;
				private array $latestIds;

				public function __construct( array &$deleteCalls, array &$selectorCalls, array $latestIds ) {
					$this->deleteCalls = &$deleteCalls;
					$this->selectorCalls = &$selectorCalls;
					$this->latestIds = $latestIds;
				}

				public function getQueryDeleter() :object {
					return new class( $this->deleteCalls ) {
						private array $calls;
						private bool $notFinished = false;
						private int $createdAt = 0;
						private string $operator = '';

						public function __construct( array &$calls ) {
							$this->calls = &$calls;
						}

						public function filterByNotFinished() :self {
							$this->notFinished = true;
							return $this;
						}

						public function filterByCreatedAt( int $createdAt, string $operator ) :self {
							$this->createdAt = $createdAt;
							$this->operator = $operator;
							return $this;
						}

						public function query() :bool {
							$this->calls[] = [
								'not_finished' => $this->notFinished,
								'created_at'   => $this->createdAt,
								'operator'     => $this->operator,
							];
							return true;
						}
					};
				}

				public function getQuerySelector() :object {
					return new class( $this->selectorCalls, $this->latestIds ) {
						private array $calls;
						private array $latestIds;
						private string $scan = '';
						private array $current = [];

						public function __construct( array &$calls, array $latestIds ) {
							$this->calls = &$calls;
							$this->latestIds = $latestIds;
						}

						public function filterByFinished() :self {
							$this->current['finished'] = true;
							return $this;
						}

						public function filterByScan( string $scan ) :self {
							$this->scan = $scan;
							$this->current['scan'] = $scan;
							return $this;
						}

						public function filterByStatus( string $status ) :self {
							$this->current['status'] = $status;
							return $this;
						}

						public function addWhereEquals( string $column, string $value ) :self {
							$this->current[ $column ] = $value;
							return $this;
						}

						public function setOrderBy( string $column, string $direction = 'DESC', bool $escape = false ) :self {
							unset( $column, $direction, $escape );
							return $this;
						}

						public function setLimit( int $limit ) :self {
							unset( $limit );
							return $this;
						}

						public function queryWithResult() :array {
							$this->calls[] = $this->current;
							$this->current = [];
							return [ (object)[ 'id' => $this->latestIds[ $this->scan ] ] ];
						}
					};
				}

				public function getTable() :string {
					return 'shield_scans';
				}
			},
		];
		$controller->comps = (object)[
			'scans' => new class {
				public function getScanSlugs() :array {
					return [ 'afs', 'apc', 'wpv' ];
				}
			},
		];

		PluginControllerInstaller::install( $controller );
	}
}
