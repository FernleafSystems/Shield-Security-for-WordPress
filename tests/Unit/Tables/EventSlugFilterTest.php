<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Tables;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\LoadLogs;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\ActivityLog\{
	BuildActivityLogTableData,
	BuildSearchPanesData
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class EventSlugFilterTest extends BaseUnitTest {

	private function createBuilder( array $slugs ) :object {
		return new class( $slugs ) extends BuildActivityLogTableData {

			private array $slugs;

			public function __construct( array $slugs ) {
				$this->slugs = $slugs;
			}

			protected function getValidEventSlugs() :array {
				return $this->slugs;
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

			public function exposeGetRecordsLoader() :LoadLogs {
				return $this->getRecordsLoader();
			}

			public function exposeGetRecords( array $wheres = [] ) :LoadLogs {
				$loader = $this->getRecordsLoader();
				$loader->wheres = \array_merge( $loader->wheres ?? [], $wheres );
				return $loader;
			}
		};
	}

	public function test_loader_includes_valid_event_slugs_in_wheres() :void {
		$builder = $this->createBuilder( [ 'login_success', 'login_fail', 'firewall_block' ] );
		$loader = $builder->exposeGetRecordsLoader();

		$this->assertNotEmpty( $loader->wheres );
		$this->assertCount( 1, $loader->wheres );
		$this->assertStringContainsString( 'event_slug', $loader->wheres[ 0 ] );
		$this->assertStringContainsString( 'login_success', $loader->wheres[ 0 ] );
		$this->assertStringContainsString( 'login_fail', $loader->wheres[ 0 ] );
		$this->assertStringContainsString( 'firewall_block', $loader->wheres[ 0 ] );
	}

	public function test_empty_slugs_no_event_filter() :void {
		$builder = $this->createBuilder( [] );
		$loader = $builder->exposeGetRecordsLoader();

		$this->assertTrue(
			!isset( $loader->wheres ) || empty( $loader->wheres ),
			'No wheres should be set when slug list is empty'
		);
	}

	public function test_single_slug_correct_format() :void {
		$builder = $this->createBuilder( [ 'login_success' ] );
		$loader = $builder->exposeGetRecordsLoader();

		$this->assertCount( 1, $loader->wheres );
		$this->assertSame(
			"`log`.`event_slug` IN ('login_success')",
			$loader->wheres[ 0 ]
		);
	}

	public function test_multiple_slugs_comma_separated() :void {
		$builder = $this->createBuilder( [ 'login_success', 'login_fail' ] );
		$loader = $builder->exposeGetRecordsLoader();

		$this->assertCount( 1, $loader->wheres );
		$this->assertSame(
			"`log`.`event_slug` IN ('login_success','login_fail')",
			$loader->wheres[ 0 ]
		);
	}

	public function test_wheres_preserved_after_adding_search_params() :void {
		$builder = $this->createBuilder( [ 'login_success', 'login_fail' ] );

		$additionalWheres = [ "`log`.`created_at` > 1000" ];
		$loader = $builder->exposeGetRecords( $additionalWheres );

		$this->assertCount( 2, $loader->wheres );
		$this->assertStringContainsString( 'event_slug', $loader->wheres[ 0 ] );
		$this->assertSame( "`log`.`created_at` > 1000", $loader->wheres[ 1 ] );
	}
}
