<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Reporting;

use Brain\Monkey\Functions;
use Carbon\Carbon;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Reports\Ops\Record;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\{
	Constants,
	CreateReportVO,
	Exceptions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	UnitTestControllerFactory
};

class CreateReportVOTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_create_uses_alert_scan_only_areas() :void {
		$this->installControllerStub( null, 'daily', 'weekly' );

		$report = $this->newCreateReportVo( Carbon::create( 2024, 4, 19, 13, 45, 0, 'UTC' ) )
			->create( Constants::REPORT_TYPE_ALERT );

		$this->assertSame( 'daily', $report->interval );
		$this->assertSame( [
			Constants::REPORT_AREA_SCANS => [ 'scan_results' ],
		], $report->areas );
		$this->assertSame( 1713398400, $report->start_at );
		$this->assertSame( 1713484799, $report->end_at );
	}

	public function test_create_uses_info_frequency_and_full_report_areas() :void {
		$this->installControllerStub( null, 'daily', 'weekly' );

		$report = $this->newCreateReportVo( Carbon::create( 2024, 4, 19, 13, 45, 0, 'UTC' ) )
			->create( Constants::REPORT_TYPE_INFO );

		$this->assertSame( 'weekly', $report->interval );
		$this->assertSame( [
			Constants::REPORT_AREA_CHANGES => [ 'plugins' ],
			Constants::REPORT_AREA_STATS   => [ 'security' ],
			Constants::REPORT_AREA_SCANS   => [ 'scan_results', 'scan_repairs' ],
		], $report->areas );
		$this->assertSame( 1712534400, $report->start_at );
		$this->assertSame( 1713139199, $report->end_at );
	}

	public function test_create_throws_duplicate_when_previous_report_already_covers_interval() :void {
		$previous = new Record();
		$previous->interval_end_at = 1713484799;
		$this->installControllerStub( $previous, 'daily', 'weekly' );

		$this->expectException( Exceptions\DuplicateReportException::class );

		$this->newCreateReportVo( Carbon::create( 2024, 4, 19, 13, 45, 0, 'UTC' ) )
			->create( Constants::REPORT_TYPE_ALERT );
	}

	public function test_create_throws_when_report_frequency_is_disabled() :void {
		$this->installControllerStub( null, 'disabled', 'weekly' );

		$this->expectException( Exceptions\ReportTypeDisabledException::class );

		$this->newCreateReportVo( Carbon::create( 2024, 4, 19, 13, 45, 0, 'UTC' ) )
			->create( Constants::REPORT_TYPE_ALERT );
	}

	private function installControllerStub( ?Record $previousRecord, string $alertInterval, string $infoInterval ) :void {
		$reportsComponent = new class( $alertInterval, $infoInterval ) {
			private string $alertInterval;
			private string $infoInterval;

			public function __construct( string $alertInterval, string $infoInterval ) {
				$this->alertInterval = $alertInterval;
				$this->infoInterval = $infoInterval;
			}

			public function getReportFrequencyAlert() :string {
				return $this->alertInterval;
			}

			public function getReportFrequencyInfo() :string {
				return $this->infoInterval;
			}

			public function getReportAreas( bool $slugsOnly = false ) :array {
				return [
					Constants::REPORT_AREA_CHANGES => [ 'plugins' ],
					Constants::REPORT_AREA_STATS   => [ 'security' ],
					Constants::REPORT_AREA_SCANS   => [ 'scan_results', 'scan_repairs' ],
				];
			}

			public function getReportTypeName( string $type ) :string {
				return $type === Constants::REPORT_TYPE_ALERT ? 'Alert' : 'Info';
			}
		};

		$reportsDb = new class( $previousRecord ) {
			private ?Record $previousRecord;

			public function __construct( ?Record $previousRecord ) {
				$this->previousRecord = $previousRecord;
			}

			public function getQuerySelector() {
				return new class( $this->previousRecord ) {
					private ?Record $previousRecord;

					public function __construct( ?Record $previousRecord ) {
						$this->previousRecord = $previousRecord;
					}

					public function filterByType( string $type ) {
						return $this;
					}

					public function filterByInterval( string $interval ) {
						return $this;
					}

					public function setOrderBy( string $column, string $order = 'DESC', bool $autoReset = false ) {
						return $this;
					}

					public function first() {
						return $this->previousRecord;
					}
				};
			}
		};

		UnitTestControllerFactory::install( null, null, (object)[
			'comps'  => (object)[
				'reports' => $reportsComponent,
			],
			'db_con' => (object)[
				'reports' => $reportsDb,
			],
		] );
	}

	private function newCreateReportVo( Carbon $now ) :CreateReportVO {
		return new class( $now ) extends CreateReportVO {
			private Carbon $now;

			public function __construct( Carbon $now ) {
				$this->now = $now;
			}

			protected function currentRequestCarbon() :Carbon {
				return clone $this->now;
			}
		};
	}
}
