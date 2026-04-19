<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\Plugin\Lib\Reporting;

use Carbon\Carbon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\{
	Constants,
	CreateReportVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Core\Request as ServicesRequest;
use FernleafSystems\Wordpress\Services\Services;

class CreateReportVOIntegrationTest extends ShieldIntegrationTestCase {

	private array $optionsSnapshot = [];

	private string $timezoneSnapshot = '';

	/** @var mixed */
	private $gmtOffsetSnapshot;

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'reports' );
		$this->loginAsSecurityAdmin();
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			'frequency_alert',
			'frequency_info',
		] );
		$this->timezoneSnapshot = (string)\get_option( 'timezone_string', '' );
		$this->gmtOffsetSnapshot = \get_option( 'gmt_offset', 0 );

		self::con()->opts
			->optSet( 'frequency_alert', 'daily' )
			->optSet( 'frequency_info', 'weekly' )
			->store();
	}

	public function tear_down() {
		if ( static::con() !== null ) {
			$this->restoreSelectedOptions( $this->optionsSnapshot );
		}
		\update_option( 'timezone_string', $this->timezoneSnapshot );
		\update_option( 'gmt_offset', $this->gmtOffsetSnapshot );
		parent::tear_down();
	}

	public function test_create_alert_uses_offset_only_wordpress_timezone_boundaries() :void {
		\update_option( 'timezone_string', '' );
		\update_option( 'gmt_offset', 5.5 );

		$report = $this->withFixedRequestTimestamp(
			Carbon::create( 2024, 4, 19, 0, 15, 0, 'UTC' )->timestamp,
			fn() => ( new CreateReportVO() )->create( Constants::REPORT_TYPE_ALERT )
		);

		$this->assertSame( 'daily', $report->interval );
		$this->assertSame( [
			Constants::REPORT_AREA_SCANS => [ 'scan_results' ],
		], $report->areas );
		$this->assertSame( Carbon::create( 2024, 4, 18, 0, 0, 0, '+05:30' )->timestamp, $report->start_at );
		$this->assertSame( Carbon::create( 2024, 4, 18, 23, 59, 59, '+05:30' )->timestamp, $report->end_at );
	}

	/**
	 * @return mixed
	 */
	private function withFixedRequestTimestamp( int $timestamp, callable $callback ) {
		$ref = new \ReflectionClass( Services::class );
		$servicesProp = $ref->getProperty( 'services' );
		$servicesProp->setAccessible( true );

		$servicesSnapshot = $servicesProp->getValue();
		$services = \is_array( $servicesSnapshot ) ? $servicesSnapshot : [];
		$services[ 'service_request' ] = new class( $timestamp ) extends ServicesRequest {

			private int $fixedTimestamp;

			public function __construct( int $fixedTimestamp ) {
				$this->fixedTimestamp = $fixedTimestamp;
				parent::__construct();
			}

			public function ts( bool $update = true ) :int {
				return $this->fixedTimestamp;
			}
		};

		$servicesProp->setValue( null, $services );

		try {
			return $callback();
		}
		finally {
			$servicesProp->setValue( null, $servicesSnapshot );
		}
	}
}
