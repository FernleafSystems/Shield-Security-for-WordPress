<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Reporting;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\{
	BuildAlertDigestContract,
	Constants,
	ReportVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	UnitTestControllerFactory,
	UnitTestPluginUrls
};

class BuildAlertDigestContractTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count ) :string => $count === 1 ? $single : $plural
		);

		UnitTestControllerFactory::install( new UnitTestPluginUrls() );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_build_filters_to_critical_scan_rows_and_groups_new_vs_outstanding() :void {
		$report = new ReportVO();
		$report->areas_data = [
			Constants::REPORT_AREA_SCANS => [
				'scan_results' => [
					[
						'slug'      => 'file_locker',
						'name'      => 'File Locker',
						'count'     => 6,
						'new_count' => 4,
						'items'     => [],
					],
					[
						'slug'      => 'afs_malware',
						'name'      => 'Malware Scan',
						'count'     => 4,
						'new_count' => 2,
						'notification_target_ids' => [ 11, 12 ],
						'items'     => [
							[ 'label' => 'malware-a.php', 'is_new' => true ],
							[ 'label' => 'malware-b.php', 'is_new' => true ],
							[ 'label' => 'legacy-shell.php', 'is_new' => false ],
						],
					],
					[
						'slug'      => 'apc',
						'name'      => 'Abandoned Plugins',
						'count'     => 2,
						'new_count' => 1,
						'notification_target_ids' => [ 23 ],
						'items'     => [
							[ 'label' => 'Old Plugin', 'is_new' => true ],
						],
					],
					[
						'slug'      => 'wpv',
						'name'      => 'Vulnerability Scan',
						'count'     => 1,
						'new_count' => 0,
						'items'     => [
							[ 'label' => 'Vulnerable Plugin', 'is_new' => false ],
						],
					],
					[
						'slug'      => 'random',
						'name'      => 'Ignore Me',
						'count'     => 8,
						'new_count' => 8,
						'items'     => [],
					],
				],
			],
		];

		$digest = ( new BuildAlertDigestContract() )->build( $report );

		$this->assertTrue( $digest[ 'has_new_items' ] );
		$this->assertSame( 3, $digest[ 'summary' ][ 'row_count' ] );
		$this->assertSame( 3, $digest[ 'summary' ][ 'new_total' ] );
		$this->assertSame( 7, $digest[ 'summary' ][ 'current_total' ] );
		$this->assertSame( 4, $digest[ 'summary' ][ 'outstanding_total' ] );
		$this->assertSame( '/admin/scans/overview?zone=scans', $digest[ 'summary' ][ 'actions_queue_href' ] );
		$this->assertSame( [ 11, 12, 23 ], $digest[ 'notification_target_ids' ] );
		$this->assertSame( [ 'Malware Scan', 'Abandoned Plugins', 'Vulnerability Scan' ], \array_column( $digest[ 'rows' ], 'title' ) );
		$this->assertSame( '4 total, 2 new', $digest[ 'rows' ][ 0 ][ 'count_summary' ] );
		$this->assertSame( 1, $digest[ 'rows' ][ 0 ][ 'hidden_outstanding_count' ] );
		$this->assertSame( 1, $digest[ 'rows' ][ 1 ][ 'hidden_outstanding_count' ] );
		$this->assertSame( [ [ 'label' => 'Vulnerable Plugin' ] ], $digest[ 'rows' ][ 2 ][ 'outstanding_items' ] );
	}
}
