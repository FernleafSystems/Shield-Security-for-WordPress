<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin\Lib\MeterAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\{
	Handler,
	Meter\MeterSummary
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\MeterAnalysisBuiltMetersCacheTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class HandlerChannelValidationTest extends BaseUnitTest {

	use MeterAnalysisBuiltMetersCacheTrait;

	protected function setUp() :void {
		parent::setUp();
		$this->setBuiltMetersCache( [
			MeterSummary::SLUG => $this->buildMeterFixture( 83 ),
		] );
		$this->setBuiltMetersByChannelCache( [] );
	}

	protected function tearDown() :void {
		$this->resetBuiltMetersCaches();
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
}
