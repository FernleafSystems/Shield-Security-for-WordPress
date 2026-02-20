<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin\Lib\MeterAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\{
	Handler,
	Meter\MeterSummary
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class HandlerChannelValidationTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		$this->setCombinedCache( [
			MeterSummary::SLUG => $this->meterFixture( 83 ),
		] );
		$this->setChannelCache( [] );
	}

	protected function tearDown() :void {
		$this->setCombinedCache( [] );
		$this->setChannelCache( [] );
		parent::tearDown();
	}

	public function test_empty_channel_value_uses_combined() :void {
		$meter = ( new Handler() )->getMeter( MeterSummary::SLUG, false, '' );
		$this->assertSame( 83, (int)$meter[ 'totals' ][ 'percentage' ] );
	}

	public function test_invalid_channel_throws() :void {
		$this->expectException( \InvalidArgumentException::class );
		( new Handler() )->getMeter( MeterSummary::SLUG, false, 'unknown-channel' );
	}

	private function meterFixture( int $percentage ) :array {
		return [
			'title'       => 'Summary',
			'subtitle'    => 'Summary',
			'warning'     => [],
			'description' => [],
			'components'  => [],
			'totals'      => [
				'score'        => 0,
				'max_weight'   => 0,
				'percentage'   => $percentage,
				'letter_score' => 'A',
			],
			'status'      => 'h',
			'rgbs'        => [ 16, 128, 0 ],
			'has_critical'=> false,
		];
	}

	private function setCombinedCache( array $cache ) :void {
		$ref = new \ReflectionClass( Handler::class );
		$prop = $ref->getProperty( 'BuiltMeters' );
		$prop->setAccessible( true );
		$prop->setValue( null, $cache );
	}

	private function setChannelCache( array $cache ) :void {
		$ref = new \ReflectionClass( Handler::class );
		$prop = $ref->getProperty( 'BuiltMetersByChannel' );
		$prop->setAccessible( true );
		$prop->setValue( null, $cache );
	}
}

