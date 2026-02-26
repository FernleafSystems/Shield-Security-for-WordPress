<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Meters\MeterCard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\{
	Component\Base as ComponentBase,
	Meter\MeterSummary
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\MeterAnalysisBuiltMetersCacheTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class MeterCardChannelTest extends BaseUnitTest {

	use MeterAnalysisBuiltMetersCacheTrait;

	protected function setUp() :void {
		parent::setUp();
		$this->setBuiltMetersCache( [
			MeterSummary::SLUG => $this->buildMeterFixture( 27 ),
		] );
		$this->setBuiltMetersByChannelCache( [
			MeterSummary::SLUG => [
				ComponentBase::CHANNEL_CONFIG => $this->buildMeterFixture( 94 ),
			],
		] );
	}

	protected function tearDown() :void {
		$this->resetBuiltMetersCaches();
		parent::tearDown();
	}

	public function test_meter_channel_config_uses_config_channel_data() :void {
		$action = new MeterCard( [
			'meter_slug'    => MeterSummary::SLUG,
			'meter_channel' => '  ConFig ',
		] );
		$meterData = $this->invokeGetMeterData( $action );

		$this->assertSame( 94, (int)$meterData[ 'totals' ][ 'percentage' ] );
	}

	public function test_empty_meter_channel_uses_default_combined_data() :void {
		$action = new MeterCard( [
			'meter_slug'    => MeterSummary::SLUG,
			'meter_channel' => '',
		] );
		$meterData = $this->invokeGetMeterData( $action );

		$this->assertSame( 27, (int)$meterData[ 'totals' ][ 'percentage' ] );
	}

	public function test_invalid_meter_channel_surfaces_strict_handler_rejection() :void {
		$this->expectException( \InvalidArgumentException::class );
		$this->invokeGetMeterData( new MeterCard( [
			'meter_slug'    => MeterSummary::SLUG,
			'meter_channel' => 'invalid-channel',
		] ) );
	}

	private function invokeGetMeterData( MeterCard $action ) :array {
		$ref = new \ReflectionMethod( $action, 'getMeterData' );
		$ref->setAccessible( true );
		return $ref->invoke( $action );
	}
}
