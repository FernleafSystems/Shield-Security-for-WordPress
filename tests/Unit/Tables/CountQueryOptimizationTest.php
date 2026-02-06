<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Tables;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\BaseBuildTableData;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class CountQueryOptimizationTest extends BaseUnitTest {

	private function createBuilder( array $overrides = [] ) :object {
		$defaults = [
			'totalCount'    => 100,
			'filteredCount' => 50,
			'wheres'        => [],
			'cacheKey'      => 'shield_dt_total_test',
		];
		$config = \array_merge( $defaults, $overrides );

		return new class( $config ) extends BaseBuildTableData {

			private array $config;

			public int $countTotalCalls = 0;

			public int $countFilteredCalls = 0;

			public function __construct( array $config ) {
				$this->config = $config;
				$this->table_data = [
					'search'      => [ 'value' => '' ],
					'searchPanes' => [],
					'start'       => 0,
					'length'      => 10,
					'order'       => [],
					'columns'     => [],
				];
			}

			protected function countTotalRecords() :int {
				$this->countTotalCalls++;
				return $this->config[ 'totalCount' ];
			}

			protected function countTotalRecordsFiltered() :int {
				$this->countFilteredCalls++;
				return $this->config[ 'filteredCount' ];
			}

			protected function buildTableRowsFromRawRecords( array $records ) :array {
				return [];
			}

			protected function getSearchPanesDataBuilder() :\FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\BaseBuildSearchPanesData {
				throw new \RuntimeException( 'Not implemented' );
			}

			protected function buildWheresFromSearchParams() :array {
				return $this->config[ 'wheres' ];
			}

			protected function getTotalCountCacheKey() :string {
				return $this->config[ 'cacheKey' ];
			}

			public function testBuild() :array {
				return $this->build();
			}
		};
	}

	public function test_filtered_count_skipped_when_no_filters() :void {
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );

		$builder = $this->createBuilder( [
			'totalCount' => 42,
			'wheres'     => [],
		] );

		$result = $builder->testBuild();

		$this->assertSame( 0, $builder->countFilteredCalls );
		$this->assertSame( 42, $result[ 'recordsFiltered' ] );
		$this->assertSame( $result[ 'recordsTotal' ], $result[ 'recordsFiltered' ] );
	}

	public function test_filtered_count_called_when_filters_active() :void {
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );

		$builder = $this->createBuilder( [
			'totalCount'    => 100,
			'filteredCount' => 25,
			'wheres'        => [ "`log`.`created_at`>1000" ],
		] );

		$result = $builder->testBuild();

		$this->assertSame( 1, $builder->countFilteredCalls );
		$this->assertSame( 25, $result[ 'recordsFiltered' ] );
		$this->assertSame( 100, $result[ 'recordsTotal' ] );
	}

	public function test_zero_total_count_not_confused_with_cache_miss() :void {
		Functions\when( 'get_transient' )->justReturn( '0' );

		$builder = $this->createBuilder( [
			'totalCount' => 99,
		] );

		$result = $builder->testBuild();

		$this->assertSame( 0, $builder->countTotalCalls );
		$this->assertSame( 0, $result[ 'recordsTotal' ] );
	}

	public function test_transient_miss_triggers_count_and_stores() :void {
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\expect( 'set_transient' )
				->once()
				->with( 'shield_dt_total_test', 77, 10 )
				->andReturn( true );

		$builder = $this->createBuilder( [
			'totalCount' => 77,
		] );

		$result = $builder->testBuild();

		$this->assertSame( 1, $builder->countTotalCalls );
		$this->assertSame( 77, $result[ 'recordsTotal' ] );
	}

	public function test_transient_hit_skips_count_query() :void {
		Functions\when( 'get_transient' )->justReturn( '200' );

		$builder = $this->createBuilder( [
			'totalCount' => 999,
		] );

		$result = $builder->testBuild();

		$this->assertSame( 0, $builder->countTotalCalls );
		$this->assertSame( 200, $result[ 'recordsTotal' ] );
	}

	public function test_cache_disabled_when_key_empty() :void {
		$builder = $this->createBuilder( [
			'totalCount' => 55,
			'cacheKey'   => '',
		] );

		$result = $builder->testBuild();

		$this->assertSame( 1, $builder->countTotalCalls );
		$this->assertSame( 55, $result[ 'recordsTotal' ] );
	}

	public function test_build_returns_correct_structure() :void {
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );

		$builder = $this->createBuilder();
		$result = $builder->testBuild();

		$this->assertArrayHasKey( 'data', $result );
		$this->assertArrayHasKey( 'recordsTotal', $result );
		$this->assertArrayHasKey( 'recordsFiltered', $result );
		$this->assertArrayHasKey( 'searchPanes', $result );
		$this->assertIsArray( $result[ 'data' ] );
		$this->assertIsInt( $result[ 'recordsTotal' ] );
		$this->assertIsInt( $result[ 'recordsFiltered' ] );
		$this->assertIsArray( $result[ 'searchPanes' ] );
	}
}
