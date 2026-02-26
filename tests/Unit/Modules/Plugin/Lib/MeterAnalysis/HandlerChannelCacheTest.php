<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin\Lib\MeterAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\{
	Component\Base as ComponentBase,
	Handler,
	Meter\MeterSummary
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class HandlerChannelCacheTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		$this->setCombinedCache( [] );
		$this->setChannelCache( [] );
	}

	protected function tearDown() :void {
		$this->setCombinedCache( [] );
		$this->setChannelCache( [] );
		parent::tearDown();
	}

	public function test_combined_and_channel_caches_are_isolated() :void {
		$this->setCombinedCache( [
			MeterSummary::SLUG => $this->meterFixture( 91 ),
		] );
		$this->setChannelCache( [
			MeterSummary::SLUG => [
				ComponentBase::CHANNEL_CONFIG => $this->meterFixture( 77 ),
				ComponentBase::CHANNEL_ACTION => $this->meterFixture( 12 ),
			],
		] );

		$handler = new Handler();
		$combined = $handler->getMeter( MeterSummary::SLUG, false );
		$config = $handler->getMeter( MeterSummary::SLUG, false, ComponentBase::CHANNEL_CONFIG );
		$action = $handler->getMeter( MeterSummary::SLUG, false, ComponentBase::CHANNEL_ACTION );

		$this->assertSame( 91, (int)$combined[ 'totals' ][ 'percentage' ] );
		$this->assertSame( 77, (int)$config[ 'totals' ][ 'percentage' ] );
		$this->assertSame( 12, (int)$action[ 'totals' ][ 'percentage' ] );
	}

	public function test_invalid_channel_throws_invalid_argument_exception() :void {
		$this->expectException( \InvalidArgumentException::class );
		( new Handler() )->getMeter( MeterSummary::SLUG, false, 'invalid-channel' );
	}

	public function test_channel_with_case_and_whitespace_is_normalized() :void {
		$this->setChannelCache( [
			MeterSummary::SLUG => [
				ComponentBase::CHANNEL_CONFIG => $this->meterFixture( 83 ),
			],
		] );

		$meter = ( new Handler() )->getMeter( MeterSummary::SLUG, false, '  ConFig  ' );
		$this->assertSame( 83, (int)( $meter[ 'totals' ][ 'percentage' ] ?? 0 ) );
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
