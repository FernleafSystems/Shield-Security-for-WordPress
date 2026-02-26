<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin\Lib\MeterAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\{
	Component\Base as ComponentBase,
	Handler,
	Meter\MeterSummary
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\MeterAnalysisBuiltMetersCacheTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class HandlerChannelCacheTest extends BaseUnitTest {

	use MeterAnalysisBuiltMetersCacheTrait;

	protected function setUp() :void {
		parent::setUp();
		$this->resetBuiltMetersCaches();
	}

	protected function tearDown() :void {
		$this->resetBuiltMetersCaches();
		parent::tearDown();
	}

	public function test_combined_and_channel_caches_are_isolated() :void {
		$this->setBuiltMetersCache( [
			MeterSummary::SLUG => $this->buildMeterFixture( 91 ),
		] );
		$this->setBuiltMetersByChannelCache( [
			MeterSummary::SLUG => [
				ComponentBase::CHANNEL_CONFIG => $this->buildMeterFixture( 77 ),
				ComponentBase::CHANNEL_ACTION => $this->buildMeterFixture( 12 ),
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
		$this->setBuiltMetersByChannelCache( [
			MeterSummary::SLUG => [
				ComponentBase::CHANNEL_CONFIG => $this->buildMeterFixture( 83 ),
			],
		] );

		$meter = ( new Handler() )->getMeter( MeterSummary::SLUG, false, '  ConFig  ' );
		$this->assertSame( 83, (int)( $meter[ 'totals' ][ 'percentage' ] ?? 0 ) );
	}
}
