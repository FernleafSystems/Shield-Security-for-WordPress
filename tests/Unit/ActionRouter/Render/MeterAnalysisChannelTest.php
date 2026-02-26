<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas\MeterAnalysis;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\{
	Component\Base as ComponentBase,
	Handler,
	Meter\MeterSummary
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class MeterAnalysisChannelTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		$this->setCombinedCache( [
			MeterSummary::SLUG => $this->meterFixture( 31 ),
		] );
		$this->setChannelCache( [
			MeterSummary::SLUG => [
				ComponentBase::CHANNEL_CONFIG => $this->meterFixture( 88 ),
			],
		] );
	}

	protected function tearDown() :void {
		$this->setCombinedCache( [] );
		$this->setChannelCache( [] );
		parent::tearDown();
	}

	public function test_offcanvas_analysis_uses_supplied_meter_channel() :void {
		$action = new MeterAnalysis( [
			'meter'         => MeterSummary::SLUG,
			'meter_channel' => '  ConFig ',
		] );

		$ref = new \ReflectionMethod( $action, 'getMeterComponents' );
		$ref->setAccessible( true );
		$components = $ref->invoke( $action );

		$this->assertSame( 88, (int)$components[ 'totals' ][ 'percentage' ] );
	}

	public function test_invalid_meter_channel_surfaces_strict_handler_rejection() :void {
		$action = new MeterAnalysis( [
			'meter'         => MeterSummary::SLUG,
			'meter_channel' => 'invalid-channel',
		] );
		$ref = new \ReflectionMethod( $action, 'getMeterComponents' );
		$ref->setAccessible( true );

		$this->expectException( \InvalidArgumentException::class );
		$ref->invoke( $action );
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
