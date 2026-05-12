<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\ReportIntervalWindowResolver;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class ReportIntervalCoverageTest extends TestCase {

	use PluginPathsTrait;

	public function test_all_configured_auto_report_intervals_are_supported_by_interval_resolver() :void {
		$configured = $this->getConfiguredAutoReportIntervals();
		$supported = ReportIntervalWindowResolver::supportedScheduledIntervals();

		$missing = \array_values( \array_diff( $configured, $supported ) );
		$this->assertSame(
			[],
			$missing,
			\sprintf(
				'Configured auto-report intervals must be supported by ReportIntervalWindowResolver. Missing: %s',
				\implode( ', ', $missing )
			)
		);
	}

	public function test_biweekly_interval_is_supported() :void {
		$this->assertContains( 'biweekly', ReportIntervalWindowResolver::supportedScheduledIntervals() );
	}

	/**
	 * @return string[]
	 */
	private function getConfiguredAutoReportIntervals() :array {
		$config = $this->decodePluginJsonFile( 'plugin.json', 'Plugin configuration' );
		$opts = (array)( $config['config_spec']['options'] ?? [] );
		$this->assertNotEmpty( $opts, 'Plugin configuration is missing config_spec.options' );

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
}
