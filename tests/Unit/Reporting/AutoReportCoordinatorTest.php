<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Reports\Ops\Record;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\{
	AutoReportCoordinator,
	Constants,
	Exceptions,
	ReportVO
};
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class AutoReportCoordinatorTest extends TestCase {

	public function test_run_persists_alert_targets_before_sending_and_still_sends_info() :void {
		$coordinator = new class extends AutoReportCoordinator {
			public array $calls = [];

			protected function createReport( string $reportType ) :ReportVO {
				$this->calls[] = 'create:'.$reportType;
				$report = new ReportVO();
				$report->type = $reportType;
				return $report;
			}

			protected function buildAndStoreReport( ReportVO $report ) :Record {
				$this->calls[] = 'build:'.$report->type;
				return new Record();
			}

			protected function persistAlertNotifications( ReportVO $report ) :bool {
				$this->calls[] = 'persist:'.$report->type;
				return true;
			}

			protected function sendNotificationEmail( ReportVO $report ) :void {
				$this->calls[] = 'send:'.$report->type;
			}
		};

		$coordinator->run();

		$this->assertSame( [
			'create:'.Constants::REPORT_TYPE_ALERT,
			'build:'.Constants::REPORT_TYPE_ALERT,
			'persist:'.Constants::REPORT_TYPE_ALERT,
			'send:'.Constants::REPORT_TYPE_ALERT,
			'create:'.Constants::REPORT_TYPE_INFO,
			'build:'.Constants::REPORT_TYPE_INFO,
			'send:'.Constants::REPORT_TYPE_INFO,
		], $coordinator->calls );
	}

	public function test_run_suppresses_alert_email_when_persistence_fails_but_still_attempts_info() :void {
		$coordinator = new class extends AutoReportCoordinator {
			public array $calls = [];

			protected function createReport( string $reportType ) :ReportVO {
				$this->calls[] = 'create:'.$reportType;
				$report = new ReportVO();
				$report->type = $reportType;
				return $report;
			}

			protected function buildAndStoreReport( ReportVO $report ) :Record {
				$this->calls[] = 'build:'.$report->type;
				return new Record();
			}

			protected function persistAlertNotifications( ReportVO $report ) :bool {
				$this->calls[] = 'persist:'.$report->type;
				return false;
			}

			protected function sendNotificationEmail( ReportVO $report ) :void {
				$this->calls[] = 'send:'.$report->type;
			}
		};

		$coordinator->run();

		$this->assertSame( [
			'create:'.Constants::REPORT_TYPE_ALERT,
			'build:'.Constants::REPORT_TYPE_ALERT,
			'persist:'.Constants::REPORT_TYPE_ALERT,
			'create:'.Constants::REPORT_TYPE_INFO,
			'build:'.Constants::REPORT_TYPE_INFO,
			'send:'.Constants::REPORT_TYPE_INFO,
		], $coordinator->calls );
	}

	public function test_run_skips_alert_persistence_and_email_when_alert_build_fails_but_still_attempts_info() :void {
		$coordinator = new class extends AutoReportCoordinator {
			public array $calls = [];

			protected function createReport( string $reportType ) :ReportVO {
				$this->calls[] = 'create:'.$reportType;
				$report = new ReportVO();
				$report->type = $reportType;
				return $report;
			}

			protected function buildAndStoreReport( ReportVO $report ) :Record {
				$this->calls[] = 'build:'.$report->type;
				if ( $report->type === Constants::REPORT_TYPE_ALERT ) {
					throw new Exceptions\ReportDataEmptyException( 'no new alerts' );
				}
				return new Record();
			}

			protected function persistAlertNotifications( ReportVO $report ) :bool {
				$this->calls[] = 'persist:'.$report->type;
				return true;
			}

			protected function sendNotificationEmail( ReportVO $report ) :void {
				$this->calls[] = 'send:'.$report->type;
			}
		};

		$coordinator->run();

		$this->assertSame( [
			'create:'.Constants::REPORT_TYPE_ALERT,
			'build:'.Constants::REPORT_TYPE_ALERT,
			'create:'.Constants::REPORT_TYPE_INFO,
			'build:'.Constants::REPORT_TYPE_INFO,
			'send:'.Constants::REPORT_TYPE_INFO,
		], $coordinator->calls );
	}
}
