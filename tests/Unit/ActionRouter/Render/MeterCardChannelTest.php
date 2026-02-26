<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Meters\MeterCard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\{
	Component\Base as ComponentBase,
	Handler,
	Meter\MeterSummary
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class MeterCardChannelTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		$this->setCombinedCache( [
			MeterSummary::SLUG => $this->meterFixture( 27 ),
		] );
		$this->setChannelCache( [
			MeterSummary::SLUG => [
				ComponentBase::CHANNEL_CONFIG => $this->meterFixture( 94 ),
			],
		] );
	}

	protected function tearDown() :void {
		$this->setCombinedCache( [] );
		$this->setChannelCache( [] );
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
