<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Tables\Scans;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Scans\{
	BuildScanTableData,
	LoadFileScanResultsTableData
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	UnitTestControllerFactory
};

class BuildScanTableDataResultsDisplayOptionsTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( 'esc_sql' )->alias( static fn( string $value ) :string => $value );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function testGetRecordsLoaderNormalizesExplicitResultsDisplayOptions() :void {
		$builder = $this->createBuilder();
		$builder->results_display_options = [
			'include_ignored'  => 'yes',
			'include_repaired' => 'false',
			'include_deleted'  => '0',
			'ignored_only'     => 1,
		];

		$loader = $builder->exposeGetRecordsLoader();

		$this->assertSame(
			[
				'include_ignored'  => true,
				'include_repaired' => false,
				'include_deleted'  => false,
				'ignored_only'     => true,
			],
			$loader->results_display_options
		);
		$this->assertSame(
			[
				"`rim`.`meta_key`='ptg_slug'",
				"`rim`.`meta_value`='akismet/akismet.php'",
			],
			$loader->custom_record_retriever_wheres
		);
	}

	public function testGetRecordsLoaderLeavesDisplayOptionsUnsetWithoutExplicitInput() :void {
		$builder = $this->createBuilder();
		$loader = $builder->exposeGetRecordsLoader();

		$this->assertNull( $loader->results_display_options ?? null );
	}

	public function testBuildExposesCleanupChangeSignalAndResetsScanCountsBeforeCounting() :void {
		$calls = (object)[
			'scanResetCalls'     => 0,
			'countResetSnapshots' => [],
		];
		UnitTestControllerFactory::install(
			null,
			null,
			(object)[
				'comps' => (object)[
					'scans' => new class( $calls ) {
						private \stdClass $calls;

						public function __construct( \stdClass $calls ) {
							$this->calls = $calls;
						}

						public function resetScanResultsCountMemoization() :void {
							$this->calls->scanResetCalls++;
						}
					},
				],
			]
		);
		$builder = $this->createBuilder( [
			new BuildScanTableDataTestLoader( [ [ 'rid' => 123 ] ], true, $calls ),
			new BuildScanTableDataTestLoader( [], false, $calls ),
		] );

		$data = $builder->build();

		$this->assertTrue( $data[ 'scan_results_changed' ] );
		$this->assertSame( [ [ 'rid' => 123 ] ], $data[ 'data' ] );
		$this->assertSame( 1, $calls->scanResetCalls );
		$this->assertSame( [ 1 ], $calls->countResetSnapshots );
	}

	public function testBuildLeavesCleanupChangeSignalFalseWhenLoadedRowsWereUnchanged() :void {
		$calls = (object)[
			'scanResetCalls'     => 0,
			'countResetSnapshots' => [],
		];
		$builder = $this->createBuilder( [
			new BuildScanTableDataTestLoader( [ [ 'rid' => 123 ] ], false, $calls ),
			new BuildScanTableDataTestLoader( [], false, $calls ),
		] );

		$data = $builder->build();

		$this->assertFalse( $data[ 'scan_results_changed' ] );
		$this->assertSame( 0, $calls->scanResetCalls );
		$this->assertSame( [ 0 ], $calls->countResetSnapshots );
	}

	private function createBuilder( array $loaders = [] ) :object {
		return new class( $loaders ) extends BuildScanTableData {

			private array $loaders;

			public function __construct( array $loaders ) {
				$this->loaders = $loaders;
				$this->type = 'plugin';
				$this->file = 'akismet/akismet.php';
				$this->table_data = [
					'search'      => [ 'value' => '' ],
					'searchPanes' => [],
					'start'       => 0,
					'length'      => 10,
					'order'       => [],
					'columns'     => [],
				];
			}

			public function exposeGetRecordsLoader() :LoadFileScanResultsTableData {
				return $this->getRecordsLoader();
			}

			protected function getRecordsLoader() :LoadFileScanResultsTableData {
				if ( $this->loaders !== [] ) {
					return \array_shift( $this->loaders );
				}

				return parent::getRecordsLoader();
			}
		};
	}
}

class BuildScanTableDataTestLoader extends LoadFileScanResultsTableData {

	private array $records;
	private bool $changed;
	private \stdClass $calls;

	public function __construct( array $records, bool $changed, \stdClass $calls ) {
		$this->records = $records;
		$this->changed = $changed;
		$this->calls = $calls;
	}

	public function run() :array {
		return $this->records;
	}

	public function countAll() :int {
		$this->calls->countResetSnapshots[] = $this->calls->scanResetCalls;
		return 7;
	}

	public function hasScanResultsChanged() :bool {
		return $this->changed;
	}
}
