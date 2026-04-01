<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Tables\Investigation;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session\LoadSessions;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Investigation\BuildSessionsData;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	ServicesState,
	UnitTestIpUtils
};

class BuildSessionsDataSearchBehaviorTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->returnArg();
		Functions\when( 'wp_strip_all_tags' )->alias(
			fn( string $content ) :string => \strip_tags( $content )
		);
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::mergeItems( [
			'service_ip' => new UnitTestIpUtils(),
		] );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function testSearchTextFiltersSessionsRowsAndCount() :void {
		$builder = $this->createBuilder( [
			[ 'rid' => 'a', 'details' => 'alpha login from 1.1.1.1' ],
			[ 'rid' => 'b', 'details' => 'beta login from 2.2.2.2' ],
			[ 'rid' => 'c', 'details' => 'gamma login from 3.3.3.3' ],
		], [
			'search' => 'beta',
		] );

		$rows = $builder->exposeLoadRecordsWithSearch();
		$this->assertCount( 1, $rows );
		$this->assertSame( 'b', $rows[ 0 ][ 'rid' ] );
		$this->assertSame( 1, $builder->exposeCountTotalRecordsFiltered() );
	}

	public function testEmptySearchKeepsFilteredCountEqualToTotal() :void {
		$builder = $this->createBuilder( [
			[ 'rid' => 'a', 'details' => 'alpha login' ],
			[ 'rid' => 'b', 'details' => 'beta login' ],
		] );

		$this->assertSame( 2, $builder->exposeCountTotalRecords() );
		$this->assertSame( 2, $builder->exposeCountTotalRecordsFiltered() );
	}

	public function testStructuredIpTokenFiltersRowsAndCount() :void {
		$builder = $this->createBuilder( [
			[ 'rid' => 'a', 'details' => 'alpha', 'ip' => '1.1.1.1' ],
			[ 'rid' => 'b', 'details' => 'beta', 'ip' => '2.2.2.2' ],
			[ 'rid' => 'c', 'details' => 'gamma', 'ip' => '3.3.3.3' ],
		], [
			'search' => 'ip:2.2.2.2',
		] );

		$rows = $builder->exposeLoadForRecords();
		$this->assertCount( 1, $rows );
		$this->assertSame( 'b', $rows[ 0 ][ 'rid' ] );
		$this->assertSame( 1, $builder->exposeCountTotalRecordsFiltered() );
	}

	public function testStructuredUserIdTokenForDifferentUserReturnsNoRows() :void {
		$builder = $this->createBuilder( [
			[ 'rid' => 'a', 'details' => 'alpha', 'ip' => '1.1.1.1' ],
		], [
			'search' => 'user_id:99',
		] );

		$this->assertSame( [], $builder->exposeLoadForRecords() );
		$this->assertSame( 0, $builder->exposeCountTotalRecordsFiltered() );
	}

	public function testMismatchedStructuredUserTokenShortCircuitsBeforeSessionLoad() :void {
		$builder = $this->createBuilder( [
			[ 'rid' => 'a', 'details' => 'alpha', 'ip' => '1.1.1.1' ],
		], [
			'search' => 'user_id:99',
		] );

		$this->assertSame( [], $builder->exposeLoadForRecords() );
		$this->assertSame( 0, $builder->exposeSessionLoads() );
	}

	public function testStructuredUsernameTokenMatchingSubjectReturnsRows() :void {
		$builder = $this->createBuilder( [
			[ 'rid' => 'a', 'details' => 'alpha', 'ip' => '1.1.1.1' ],
		], [
			'search'      => 'user_name:admin',
			'usernameMap' => [ 'admin' => 42 ],
		] );

		$this->assertCount( 1, $builder->exposeLoadForRecords() );
		$this->assertSame( 1, $builder->exposeCountTotalRecordsFiltered() );
	}

	public function testIpSubjectBuildReportsFilteredCountForFreeTextSearch() :void {
		$builder = $this->createBuilder( [
			[
				'rid'    => 'a',
				'details'=> 'alpha session',
				'ip'     => '203.0.113.88',
				'shield' => [ 'user_id' => 41, 'ip' => '203.0.113.88' ],
			],
			[
				'rid'    => 'b',
				'details'=> 'beta session',
				'ip'     => '203.0.113.88',
				'shield' => [ 'user_id' => 42, 'ip' => '203.0.113.88' ],
			],
			[
				'rid'    => 'c',
				'details'=> 'gamma session',
				'ip'     => '203.0.113.89',
				'shield' => [ 'user_id' => 43, 'ip' => '203.0.113.89' ],
			],
		], [
			'search'     => 'beta',
			'subjectType'=> 'ip',
			'subjectId'  => '203.0.113.88',
		] );

		$result = $builder->build();

		$this->assertSame( 2, $result[ 'recordsTotal' ] );
		$this->assertSame( 1, $result[ 'recordsFiltered' ] );
		$this->assertCount( 1, $result[ 'data' ] );
		$this->assertSame( 'b', $result[ 'data' ][ 0 ][ 'rid' ] );
	}

	public function testIpSubjectSearchPaneUidFiltersRowsAndCount() :void {
		$builder = $this->createBuilder( [
			[
				'rid'    => 'a',
				'uid'    => 41,
				'details'=> 'alpha session',
				'ip'     => '203.0.113.88',
				'shield' => [ 'user_id' => 41, 'ip' => '203.0.113.88' ],
			],
			[
				'rid'    => 'b',
				'uid'    => 42,
				'details'=> 'beta session',
				'ip'     => '203.0.113.88',
				'shield' => [ 'user_id' => 42, 'ip' => '203.0.113.88' ],
			],
		], [
			'subjectType' => 'ip',
			'subjectId'   => '203.0.113.88',
			'searchPanes' => [ 'uid' => [ '42' ] ],
		] );

		$result = $builder->build();

		$this->assertSame( 2, $result[ 'recordsTotal' ] );
		$this->assertSame( 1, $result[ 'recordsFiltered' ] );
		$this->assertCount( 1, $result[ 'data' ] );
		$this->assertSame( 42, $result[ 'data' ][ 0 ][ 'uid' ] );
	}

	public function testIpSubjectMismatchedPaneAndStructuredUserFiltersReturnNoRows() :void {
		$builder = $this->createBuilder( [
			[
				'rid'    => 'a',
				'uid'    => 42,
				'details'=> 'alpha session',
				'ip'     => '203.0.113.88',
				'shield' => [ 'user_id' => 42, 'ip' => '203.0.113.88' ],
			],
		], [
			'search'     => 'user_id:99',
			'subjectType'=> 'ip',
			'subjectId'  => '203.0.113.88',
			'searchPanes'=> [ 'uid' => [ '42' ] ],
		] );

		$result = $builder->build();

		$this->assertSame( 1, $result[ 'recordsTotal' ] );
		$this->assertSame( 0, $result[ 'recordsFiltered' ] );
		$this->assertSame( [], $result[ 'data' ] );
	}

	public function testUnsupportedSubjectReturnsNoRowsOrCounts() :void {
		$builder = $this->createBuilder( [
			[
				'rid'    => 'a',
				'details'=> 'alpha session',
				'ip'     => '203.0.113.88',
				'shield' => [ 'user_id' => 42, 'ip' => '203.0.113.88' ],
			],
		], [
			'subjectType' => 'plugin',
			'subjectId'   => 'example/example.php',
		] );

		$result = $builder->build();

		$this->assertSame( 0, $result[ 'recordsTotal' ] );
		$this->assertSame( 0, $result[ 'recordsFiltered' ] );
		$this->assertSame( [], $result[ 'data' ] );
	}

	private function createBuilder( array $rows, array $config = [] ) :BuildSessionsData {
		return new class( $rows, $config ) extends BuildSessionsData {

			private array $rows;
			private array $usernameMap;
			private array $emailMap;
			private \stdClass $counters;

			public function __construct( array $rows, array $config ) {
				$this->rows = $rows;
				$this->usernameMap = $config[ 'usernameMap' ] ?? [];
				$this->emailMap = $config[ 'emailMap' ] ?? [];
				$this->counters = (object)[
					'sessionLoads' => 0,
				];
				$this->setSubject(
					(string)( $config[ 'subjectType' ] ?? 'user' ),
					$config[ 'subjectId' ] ?? 42
				);
				$this->table_data = [
					'search'      => [ 'value' => (string)( $config[ 'search' ] ?? '' ) ],
					'searchPanes' => $config[ 'searchPanes' ] ?? [],
					'start'       => (int)( $config[ 'start' ] ?? 0 ),
					'length'      => (int)( $config[ 'length' ] ?? 10 ),
					'order'       => [],
					'columns'     => [],
				];
			}

			protected function getUserIdFromSearchUsername( string $username ) :int {
				return (int)( $this->usernameMap[ $username ] ?? 0 );
			}

			protected function getUserIdFromSearchEmail( string $email ) :int {
				return (int)( $this->emailMap[ $email ] ?? 0 );
			}

			protected function buildTableRowsFromRawRecords( array $records ) :array {
				return \array_values( $records );
			}

			protected function getRecordsLoader() :LoadSessions {
				return new class( $this->rows, $this->counters ) extends LoadSessions {

					private array $rows;
					private \stdClass $counters;

					public function __construct( array $rows, \stdClass $counters ) {
						$this->rows = $rows;
						$this->counters = $counters;
					}

					public function count() :int {
						return \count( $this->rows );
					}

					public function allOrderedByLastActivityAt() :array {
						$this->counters->sessionLoads++;
						return $this->rows;
					}
				};
			}

			public function exposeLoadRecordsWithSearch() :array {
				return $this->loadRecordsWithSearch();
			}

			public function exposeCountTotalRecordsFiltered() :int {
				return $this->countTotalRecordsFiltered();
			}

			public function exposeCountTotalRecords() :int {
				return $this->countTotalRecords();
			}

			public function exposeLoadForRecords() :array {
				return $this->loadForRecords();
			}

			public function exposeSessionLoads() :int {
				return (int)$this->counters->sessionLoads;
			}
		};
	}
}
