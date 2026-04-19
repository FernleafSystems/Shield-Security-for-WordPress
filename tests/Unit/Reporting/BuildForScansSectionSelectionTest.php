<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\{
	Constants,
	ReportVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Data\BuildForScans;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class BuildForScansSectionSelectionTest extends TestCase {

	public function test_build_only_includes_requested_scan_subsections() :void {
		$report = new ReportVO();
		$report->areas = [
			Constants::REPORT_AREA_SCANS => [ 'scan_results' ],
		];

		$builder = new class( $report ) extends BuildForScans {
			protected function buildMergedResults() :array {
				return [ 'results' => true ];
			}

			protected function buildForRepairs() :array {
				return [ 'repairs' => true ];
			}
		};

		$this->assertSame( [
			'scan_results' => [ 'results' => true ],
		], $builder->build() );
	}

	public function test_build_defaults_to_both_scan_subsections_for_legacy_truthy_area_flag() :void {
		$report = new ReportVO();
		$report->areas = [
			Constants::REPORT_AREA_SCANS => true,
		];

		$builder = new class( $report ) extends BuildForScans {
			protected function buildMergedResults() :array {
				return [ 'results' => true ];
			}

			protected function buildForRepairs() :array {
				return [ 'repairs' => true ];
			}
		};

		$this->assertSame( [
			'scan_results' => [ 'results' => true ],
			'scan_repairs' => [ 'repairs' => true ],
		], $builder->build() );
	}
}
