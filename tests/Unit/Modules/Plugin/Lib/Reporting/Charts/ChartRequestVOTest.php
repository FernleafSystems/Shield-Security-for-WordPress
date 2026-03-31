<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin\Lib\Reporting\Charts;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Charts\{
	ChartOptions,
	ChartRequestVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ChartRequestVOTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'sanitize_key' )->alias(
			static fn( string $key ) :string => \strtolower( \trim( $key ) )
		);
	}

	public function test_apply_from_array_normalizes_supported_request_contract() :void {
		$req = ( new ChartRequestVO() )->applyFromArray( [
			'period_key' => 'unknown-period',
			'event_keys' => [
				'block_xml',
				'unknown_event',
				'login_block',
				'ip_blocked',
			],
			'ignore_me' => 'value',
		] );

		$this->assertSame( ChartOptions::defaultPeriodKey(), $req->period_key );
		$this->assertSame(
			[
				'ip_blocked',
				'login_block',
				'block_xml',
			],
			$req->event_keys
		);
		$this->assertSame(
			[
				'period_key' => ChartOptions::defaultPeriodKey(),
				'event_keys' => [
					'ip_blocked',
					'login_block',
					'block_xml',
				],
			],
			$req->getRawData()
		);
	}

	public function test_apply_from_array_coerces_non_array_event_keys_to_empty_list() :void {
		$req = ( new ChartRequestVO() )->applyFromArray( [
			'period_key' => ChartOptions::PERIOD_7_DAYS,
			'event_keys' => 'not-an-array',
		] );

		$this->assertSame( ChartOptions::PERIOD_7_DAYS, $req->period_key );
		$this->assertSame( [], $req->event_keys );
	}
}
