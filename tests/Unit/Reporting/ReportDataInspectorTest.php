<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\{
	Constants,
	ReportDataInspector
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ReportDataInspectorTest extends BaseUnitTest {

	public function test_email_display_filters_prune_empty_change_and_repair_zones() :void {
		$inspector = new ReportDataInspector( [
			Constants::REPORT_AREA_SCANS   => [
				'scan_repairs' => [
					'auto_repair' => [
						'name'    => 'Automatic Repairs',
						'count'   => 2,
						'repairs' => [ '/wp-content/plugins/example/example.php' ],
					],
					'empty_repair' => [
						'name'    => 'Empty Repairs',
						'count'   => 0,
						'repairs' => [],
					],
				],
			],
			Constants::REPORT_AREA_CHANGES => [
				'plugins' => [
					'title' => 'Plugins',
					'total' => 1,
				],
				'users'   => [
					'title' => 'Users',
					'total' => 0,
				],
			],
		] );

		$this->assertSame( [ 'plugins' ], \array_keys( $inspector->getChangesForEmailDisplay() ) );
		$this->assertSame( [ 'auto_repair' ], \array_keys( $inspector->getScanRepairsForEmailDisplay() ) );
	}
}
