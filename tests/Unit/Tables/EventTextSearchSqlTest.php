<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Tables;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\ActivityLog\{
	BuildActivityLogTableData,
	BuildSearchPanesData
};
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\ImpossibleQueryException;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

/**
 * Tests the SQL generation in buildSqlWhereForEventTextSearch().
 *
 * Paths that require the plugin controller (meta EXISTS clause, mixed slug+meta clause)
 * cannot be tested here and should be covered by integration tests.
 */
class EventTextSearchSqlTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\stubs( [
			'_x' => fn( $text ) => $text,
		] );
	}

	private function createBuilder( string $searchRemaining ) :object {
		return new class( $searchRemaining ) extends BuildActivityLogTableData {

			private string $remaining;

			public function __construct( string $remaining ) {
				$this->remaining = $remaining;
			}

			protected function parseSearchText() :array {
				return [
					'remaining' => $this->remaining,
					'ip'        => '',
					'user_id'   => '',
					'user_name' => '',
					'user_email' => '',
				];
			}

			protected function getValidEventSlugs() :array {
				return [];
			}

			protected function countTotalRecords() :int {
				return 0;
			}

			protected function countTotalRecordsFiltered() :int {
				return 0;
			}

			protected function buildTableRowsFromRawRecords( array $records ) :array {
				return [];
			}

			protected function getSearchPanesDataBuilder() :BuildSearchPanesData {
				throw new \RuntimeException( 'Not implemented' );
			}
		};
	}

	/**
	 * @return mixed
	 */
	private function invokeEventTextSearch( object $builder ) {
		$method = new \ReflectionMethod( $builder, 'buildSqlWhereForEventTextSearch' );
		$method->setAccessible( true );
		return $method->invoke( $builder );
	}

	public function test_empty_search_returns_empty_string() :void {
		$result = $this->invokeEventTextSearch( $this->createBuilder( '' ) );
		$this->assertSame( '', $result );
	}

	public function test_all_stopwords_throws_impossible_query() :void {
		$this->expectException( ImpossibleQueryException::class );
		$this->invokeEventTextSearch( $this->createBuilder( 'the was for from' ) );
	}

	public function test_all_short_words_throws_impossible_query() :void {
		$this->expectException( ImpossibleQueryException::class );
		$this->invokeEventTextSearch( $this->createBuilder( 'ab cd ef' ) );
	}

	public function test_mixed_stopwords_and_short_words_throws_impossible_query() :void {
		$this->expectException( ImpossibleQueryException::class );
		$this->invokeEventTextSearch( $this->createBuilder( 'the ab was cd' ) );
	}

	public function test_result_is_memoized_across_calls() :void {
		$builder = $this->createBuilder( '' );
		$result1 = $this->invokeEventTextSearch( $builder );
		$result2 = $this->invokeEventTextSearch( $builder );
		$this->assertSame( '', $result1 );
		$this->assertSame( $result1, $result2 );
	}
}
