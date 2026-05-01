<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Reporting;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\{
	Constants,
	CustomReportAreaNormalizer
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class CustomReportAreaNormalizerTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( 'sanitize_key' )->alias(
			static fn( string $key ) :string => \strtolower( \trim( $key ) )
		);
	}

	public function test_normalize_keeps_allowed_area_values_in_canonical_order() :void {
		$normalized = $this->normalizer()->normalize( [
			'changes_zones'    => [
				' themes ',
				'unknown',
				'plugins',
			],
			'statistics_zones' => [
				'wordpress',
				'security',
			],
			'scans_zones'      => [
				'scan_repairs',
			],
		] );

		$this->assertSame( [
			Constants::REPORT_AREA_CHANGES => [
				'plugins',
				'themes',
			],
			Constants::REPORT_AREA_STATS   => [
				'security',
				'wordpress',
			],
			Constants::REPORT_AREA_SCANS   => [
				'scan_repairs',
			],
		], $normalized );
	}

	public function test_normalize_omits_empty_unknown_and_malformed_areas() :void {
		$normalized = $this->normalizer()->normalize( [
			'changes_zones'    => 'plugins',
			'statistics_zones' => [
				'unknown',
			],
			'scans_zones'      => [],
		] );

		$this->assertSame( [], $normalized );
	}

	private function normalizer() :CustomReportAreaNormalizer {
		return new CustomReportAreaNormalizer( [
			Constants::REPORT_AREA_CHANGES => [
				'plugins',
				'themes',
			],
			Constants::REPORT_AREA_STATS   => [
				'security',
				'wordpress',
			],
			Constants::REPORT_AREA_SCANS   => [
				'scan_results',
				'scan_repairs',
			],
		] );
	}
}
