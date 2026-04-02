<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Tables\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Scans\{
	BuildScanTableData,
	LoadFileScanResultsTableData
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class BuildScanTableDataResultsDisplayOptionsTest extends BaseUnitTest {

	public function testGetRecordsLoaderNormalizesExplicitResultsDisplayOptions() :void {
		$builder = $this->createBuilder();
		$builder->results_display_options = [
			'include_ignored' => 'yes',
			'ignored_only'    => 1,
		];

		$loader = $builder->exposeGetRecordsLoader();

		$this->assertSame(
			[
				'include_ignored' => true,
				'ignored_only'    => true,
			],
			$loader->results_display_options
		);
	}

	public function testGetRecordsLoaderLeavesDisplayOptionsUnsetWithoutExplicitInput() :void {
		$builder = $this->createBuilder();
		$loader = $builder->exposeGetRecordsLoader();

		$this->assertNull( $loader->results_display_options ?? null );
	}

	private function createBuilder() :object {
		return new class extends BuildScanTableData {

			public function __construct() {
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
		};
	}
}
