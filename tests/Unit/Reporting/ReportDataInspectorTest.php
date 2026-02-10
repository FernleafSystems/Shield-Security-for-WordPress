<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\ReportDataInspector;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class ReportDataInspectorTest extends TestCase {

	public function test_count_scan_results_new_sums_new_count_field() :void {
		$inspector = new ReportDataInspector( [
			Constants::REPORT_AREA_SCANS => [
				'scan_results' => [
					[ 'count' => 5, 'new_count' => 2 ],
					[ 'count' => 3, 'new_count' => 1 ],
					[ 'count' => 0, 'new_count' => 0 ],
				],
			],
		] );
		$this->assertSame( 3, $inspector->countScanResultsNew() );
	}

	public function test_count_scan_results_current_sums_count_field() :void {
		$inspector = new ReportDataInspector( [
			Constants::REPORT_AREA_SCANS => [
				'scan_results' => [
					[ 'count' => 5, 'new_count' => 2 ],
					[ 'count' => 3, 'new_count' => 1 ],
					[ 'count' => 0, 'new_count' => 0 ],
				],
			],
		] );
		$this->assertSame( 8, $inspector->countScanResultsCurrent() );
	}

	public function test_count_scan_results_handles_missing_scans_area() :void {
		$inspector = new ReportDataInspector( [] );
		$this->assertSame( 0, $inspector->countScanResultsNew() );
		$this->assertSame( 0, $inspector->countScanResultsCurrent() );
	}

	public function test_count_scan_results_handles_missing_scan_results_key() :void {
		$inspector = new ReportDataInspector( [
			Constants::REPORT_AREA_SCANS => [
				'scan_repairs' => [ 'some_repair' => [ 'count' => 2 ] ],
			],
		] );
		$this->assertSame( 0, $inspector->countScanResultsNew() );
		$this->assertSame( 0, $inspector->countScanResultsCurrent() );
	}

	public function test_count_scan_results_handles_missing_field_gracefully() :void {
		$inspector = new ReportDataInspector( [
			Constants::REPORT_AREA_SCANS => [
				'scan_results' => [
					[ 'count' => 5 ],
				],
			],
		] );
		$this->assertSame( 0, $inspector->countScanResultsNew() );
		$this->assertSame( 5, $inspector->countScanResultsCurrent() );
	}

	public function test_count_all_aggregates_all_areas() :void {
		$inspector = new ReportDataInspector( [
			Constants::REPORT_AREA_SCANS => [
				'scan_results' => [
					[ 'count' => 3, 'new_count' => 1 ],
				],
			],
			Constants::REPORT_AREA_STATS => [
				'security' => [ 'has_non_zero_stat' => true ],
				'empty'    => [ 'has_non_zero_stat' => false ],
			],
			Constants::REPORT_AREA_CHANGES => [
				'plugins' => [ 'total' => 2 ],
				'themes'  => [ 'total' => 0 ],
			],
		] );
		// new_count(1) + count(3) + stats_zones(1) + change_zones(1) = 6
		$this->assertSame( 6, $inspector->countAll() );
	}

	public function test_count_all_returns_zero_for_empty_data() :void {
		$inspector = new ReportDataInspector( [] );
		$this->assertSame( 0, $inspector->countAll() );
	}

	public function test_count_stat_zones_with_non_zero_stats() :void {
		$inspector = new ReportDataInspector( [
			Constants::REPORT_AREA_STATS => [
				'zone_a' => [ 'has_non_zero_stat' => true ],
				'zone_b' => [ 'has_non_zero_stat' => true ],
				'zone_c' => [ 'has_non_zero_stat' => false ],
			],
		] );
		$this->assertSame( 2, $inspector->countStatZonesWithNonZeroStats() );
	}

	public function test_get_stats_for_email_display_filters_zero_stats() :void {
		$inspector = new ReportDataInspector( [
			Constants::REPORT_AREA_STATS => [
				'security' => [
					'title'             => 'Security Stats',
					'has_non_zero_stat' => true,
					'stats'             => [
						'zero'    => [ 'is_zero_stat' => true, 'count_diff_abs' => 0 ],
						'same'    => [ 'is_zero_stat' => false, 'count_diff_abs' => 0 ],
						'changed' => [ 'is_zero_stat' => false, 'count_diff_abs' => 2 ],
					],
				],
			],
		] );
		$stats = $inspector->getStatsForEmailDisplay();

		$this->assertArrayHasKey( 'security', $stats );
		$this->assertArrayNotHasKey( 'zero', $stats[ 'security' ][ 'stats' ] );
		$this->assertArrayHasKey( 'same', $stats[ 'security' ][ 'stats' ] );
		$this->assertArrayHasKey( 'changed', $stats[ 'security' ][ 'stats' ] );
	}

	public function test_get_stats_for_email_display_filters_unchanged_stats_when_not_detailed() :void {
		$inspector = new ReportDataInspector( [
			Constants::REPORT_AREA_STATS => [
				'security' => [
					'title'             => 'Security Stats',
					'has_non_zero_stat' => true,
					'stats'             => [
						'same'    => [ 'is_zero_stat' => false, 'count_diff_abs' => 0 ],
						'changed' => [ 'is_zero_stat' => false, 'count_diff_abs' => 2 ],
					],
				],
			],
		] );
		$stats = $inspector->getStatsForEmailDisplay( 'summary' );

		$this->assertArrayHasKey( 'security', $stats );
		$this->assertArrayNotHasKey( 'same', $stats[ 'security' ][ 'stats' ] );
		$this->assertArrayHasKey( 'changed', $stats[ 'security' ][ 'stats' ] );
	}

	public function test_get_stats_for_email_display_removes_empty_groups() :void {
		$inspector = new ReportDataInspector( [
			Constants::REPORT_AREA_STATS => [
				'security' => [
					'title'             => 'Security Stats',
					'has_non_zero_stat' => true,
					'stats'             => [
						'same' => [ 'is_zero_stat' => false, 'count_diff_abs' => 0 ],
					],
				],
				'wordpress' => [
					'title'             => 'WordPress Stats',
					'has_non_zero_stat' => true,
					'stats'             => [
						'changed' => [ 'is_zero_stat' => false, 'count_diff_abs' => 1 ],
					],
				],
			],
		] );
		$stats = $inspector->getStatsForEmailDisplay( 'summary' );

		$this->assertArrayNotHasKey( 'security', $stats );
		$this->assertArrayHasKey( 'wordpress', $stats );
	}

	public function test_get_stats_for_email_display_returns_empty_when_no_rows_match() :void {
		$inspector = new ReportDataInspector( [
			Constants::REPORT_AREA_STATS => [
				'security' => [
					'title'             => 'Security Stats',
					'has_non_zero_stat' => true,
					'stats'             => [
						'same' => [ 'is_zero_stat' => false, 'count_diff_abs' => 0 ],
					],
				],
			],
		] );

		$this->assertSame( [], $inspector->getStatsForEmailDisplay( 'summary' ) );
	}

	public function test_count_change_zones_with_changes() :void {
		$inspector = new ReportDataInspector( [
			Constants::REPORT_AREA_CHANGES => [
				'zone_a' => [ 'total' => 5 ],
				'zone_b' => [ 'total' => 0 ],
				'zone_c' => [ 'total' => 1 ],
			],
		] );
		$this->assertSame( 2, $inspector->countChangeZonesWithChanges() );
	}
}
