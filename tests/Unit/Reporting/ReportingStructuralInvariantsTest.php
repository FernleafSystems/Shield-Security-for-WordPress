<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Exceptions;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Guards structural invariants that ReportGenerator::auto() depends on.
 *
 * auto() catches ReportBuildException to handle all report-related failures uniformly.
 * If any subclass stops extending ReportBuildException, the catch blocks silently miss
 * exceptions and the method behaves incorrectly (e.g. sending duplicate emails).
 *
 * auto() also depends on alert and info having distinct type values so that
 * setPreviousReport() queries are type-isolated: storing an info record after an
 * alert fires won't interfere with the alert's own duplicate detection, and vice versa.
 */
class ReportingStructuralInvariantsTest extends TestCase {

	/**
	 * @dataProvider providerReportBuildExceptionSubclasses
	 */
	public function test_exception_extends_report_build_exception( string $exceptionClass ) :void {
		$this->assertTrue(
			\is_a( $exceptionClass, Exceptions\ReportBuildException::class, true ),
			$exceptionClass.' must extend ReportBuildException for auto() catch blocks to work'
		);
	}

	public static function providerReportBuildExceptionSubclasses() :array {
		return [
			'DuplicateReportException'    => [ Exceptions\DuplicateReportException::class ],
			'ReportDataEmptyException'    => [ Exceptions\ReportDataEmptyException::class ],
			'ReportTypeDisabledException' => [ Exceptions\ReportTypeDisabledException::class ],
		];
	}

	public function test_alert_and_info_type_values_are_distinct() :void {
		$this->assertNotSame(
			Constants::REPORT_TYPE_ALERT,
			Constants::REPORT_TYPE_INFO,
			'Alert and info must have distinct DB type values for type-isolated duplicate detection'
		);
	}

	public function test_all_report_type_values_are_unique() :void {
		$types = [
			Constants::REPORT_TYPE_ALERT,
			Constants::REPORT_TYPE_INFO,
			Constants::REPORT_TYPE_CUSTOM,
		];
		$this->assertCount(
			\count( $types ),
			\array_unique( $types ),
			'All report type constants must have unique values'
		);
	}
}
