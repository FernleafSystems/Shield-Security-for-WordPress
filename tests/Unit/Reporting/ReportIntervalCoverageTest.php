<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class ReportIntervalCoverageTest extends TestCase {

	use PluginPathsTrait;

	public function test_all_configured_auto_report_intervals_are_supported_by_create_report_vo() :void {
		$configured = $this->getConfiguredAutoReportIntervals();
		$supported = $this->getSupportedIntervalsFromCreateReportVO();

		$missing = \array_values( \array_diff( $configured, $supported ) );
		$this->assertSame(
			[],
			$missing,
			\sprintf(
				'Configured auto-report intervals must be supported by CreateReportVO::setIntervalBoundaries(). Missing: %s',
				\implode( ', ', $missing )
			)
		);
	}

	public function test_biweekly_interval_is_supported() :void {
		$this->assertContains( 'biweekly', $this->getSupportedIntervalsFromCreateReportVO() );
	}

	/**
	 * @return string[]
	 */
	private function getConfiguredAutoReportIntervals() :array {
		$opts = $this->decodePluginJsonFile( 'plugin-spec/34_options.json', 'Options spec' );

		$intervals = [];
		foreach ( $opts as $opt ) {
			if ( !\in_array( $opt[ 'key' ] ?? '', [ 'frequency_alert', 'frequency_info' ], true ) ) {
				continue;
			}

			foreach ( (array)( $opt[ 'value_options' ] ?? [] ) as $valueOption ) {
				$interval = (string)( $valueOption[ 'value_key' ] ?? '' );
				if ( $interval !== '' && $interval !== 'disabled' ) {
					$intervals[ $interval ] = true;
				}
			}
		}

		return \array_keys( $intervals );
	}

	/**
	 * @return string[]
	 */
	private function getSupportedIntervalsFromCreateReportVO() :array {
		$source = $this->getPluginFileContents(
			'src/Modules/Plugin/Lib/Reporting/CreateReportVO.php',
			'CreateReportVO source'
		);

		$methodStart = \strpos( $source, 'private function setIntervalBoundaries() :self {' );
		$this->assertNotFalse( $methodStart, 'Could not find CreateReportVO::setIntervalBoundaries() in source.' );

		$methodEnd = \strpos( $source, 'if ( $this->rep->previous instanceof ReportsDB\Record', (int)$methodStart );
		$this->assertNotFalse( $methodEnd, 'Could not find end marker for CreateReportVO::setIntervalBoundaries().' );

		$methodBody = \substr( $source, (int)$methodStart, (int)$methodEnd - (int)$methodStart );
		\preg_match_all( "/case '([a-z_]+)'\\s*:/", $methodBody, $matches );

		return \array_values( \array_unique( $matches[ 1 ] ?? [] ) );
	}
}
