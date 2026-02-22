<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Tables\Investigation;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session\LoadSessions;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Investigation\BuildSessionsData;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class BuildSessionsDataSearchBehaviorTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->returnArg();
		Functions\when( 'wp_strip_all_tags' )->alias(
			fn( string $content ) :string => \strip_tags( $content )
		);
	}

	public function testSearchTextFiltersSessionsRowsAndCount() :void {
		$builder = $this->createBuilder(
			[
				[ 'rid' => 'a', 'details' => 'alpha login from 1.1.1.1' ],
				[ 'rid' => 'b', 'details' => 'beta login from 2.2.2.2' ],
				[ 'rid' => 'c', 'details' => 'gamma login from 3.3.3.3' ],
			],
			'beta',
			0,
			10
		);

		$rows = $builder->exposeLoadRecordsWithSearch();
		$this->assertCount( 1, $rows );
		$this->assertSame( 'b', $rows[ 0 ][ 'rid' ] );
		$this->assertSame( 1, $builder->exposeCountTotalRecordsFiltered() );
	}

	public function testEmptySearchKeepsFilteredCountEqualToTotal() :void {
		$builder = $this->createBuilder(
			[
				[ 'rid' => 'a', 'details' => 'alpha login' ],
				[ 'rid' => 'b', 'details' => 'beta login' ],
			],
			'',
			0,
			10
		);

		$this->assertSame( 2, $builder->exposeCountTotalRecords() );
		$this->assertSame( 2, $builder->exposeCountTotalRecordsFiltered() );
	}

	public function testStructuredIpTokenFiltersRowsAndCount() :void {
		$builder = $this->createBuilder(
			[
				[ 'rid' => 'a', 'details' => 'alpha', 'ip' => '1.1.1.1' ],
				[ 'rid' => 'b', 'details' => 'beta', 'ip' => '2.2.2.2' ],
				[ 'rid' => 'c', 'details' => 'gamma', 'ip' => '3.3.3.3' ],
			],
			'ip:2.2.2.2',
			0,
			10
		);

		$rows = $builder->exposeLoadForRecords();
		$this->assertCount( 1, $rows );
		$this->assertSame( 'b', $rows[ 0 ][ 'rid' ] );
		$this->assertSame( 1, $builder->exposeCountTotalRecordsFiltered() );
	}

	public function testStructuredUserIdTokenForDifferentUserReturnsNoRows() :void {
		$builder = $this->createBuilder(
			[
				[ 'rid' => 'a', 'details' => 'alpha', 'ip' => '1.1.1.1' ],
			],
			'user_id:99',
			0,
			10
		);

		$this->assertSame( [], $builder->exposeLoadForRecords() );
		$this->assertSame( 0, $builder->exposeCountTotalRecordsFiltered() );
	}

	public function testMismatchedStructuredUserTokenShortCircuitsBeforeRecordsLoad() :void {
		$builder = $this->createBuilder(
			[
				[ 'rid' => 'a', 'details' => 'alpha', 'ip' => '1.1.1.1' ],
			],
			'user_id:99',
			0,
			10
		);

		$this->assertSame( [], $builder->exposeLoadForRecords() );
		$this->assertSame( 0, $builder->exposeRecordLoads() );
	}

	public function testStructuredUsernameTokenMatchingSubjectReturnsRows() :void {
		$builder = $this->createBuilder(
			[
				[ 'rid' => 'a', 'details' => 'alpha', 'ip' => '1.1.1.1' ],
			],
			'user_name:admin',
			0,
			10,
			[ 'admin' => 42 ]
		);

		$this->assertCount( 1, $builder->exposeLoadForRecords() );
		$this->assertSame( 1, $builder->exposeCountTotalRecordsFiltered() );
	}

	private function createBuilder(
		array $rows,
		string $search,
		int $start,
		int $length,
		array $usernameMap = [],
		array $emailMap = []
	) :BuildSessionsData {
		return new class( $rows, $search, $start, $length, $usernameMap, $emailMap ) extends BuildSessionsData {

			private array $rows;
			private array $usernameMap;
			private array $emailMap;
			private int $recordLoads = 0;

			public function __construct(
				array $rows,
				string $search,
				int $start,
				int $length,
				array $usernameMap,
				array $emailMap
			) {
				$this->rows = $rows;
				$this->usernameMap = $usernameMap;
				$this->emailMap = $emailMap;
				$this->setSubject( 'user', 42 );
				$this->table_data = [
					'search'  => [ 'value' => $search ],
					'start'   => $start,
					'length'  => $length,
					'order'   => [],
					'columns' => [],
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

			protected function getRecords( array $wheres = [], int $offset = 0, int $limit = 0 ) :array {
				$this->recordLoads++;
				return \array_slice(
					$this->rows,
					$offset,
					$limit > 0 ? $limit : null
				);
			}

			protected function getRecordsLoader() :LoadSessions {
				return new class( $this->rows ) extends LoadSessions {

					private array $rows;

					public function __construct( array $rows ) {
						$this->rows = $rows;
					}

					public function count() :int {
						return \count( $this->rows );
					}

					public function allOrderedByLastActivityAt() :array {
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

			public function exposeRecordLoads() :int {
				return $this->recordLoads;
			}
		};
	}
}
