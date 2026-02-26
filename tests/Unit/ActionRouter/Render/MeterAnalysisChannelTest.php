<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas\MeterAnalysis;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\{
	Component\Base as ComponentBase,
	Meter\MeterSummary
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\MeterAnalysisBuiltMetersCacheTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\InvokesNonPublicMethods;

class MeterAnalysisChannelTest extends BaseUnitTest {

	use MeterAnalysisBuiltMetersCacheTrait;
	use InvokesNonPublicMethods;

	protected function setUp() :void {
		parent::setUp();
		$this->setBuiltMetersCache( [
			MeterSummary::SLUG => $this->buildMeterFixture( 31 ),
		] );
		$this->setBuiltMetersByChannelCache( [
			MeterSummary::SLUG => [
				ComponentBase::CHANNEL_CONFIG => $this->buildMeterFixture( 88 ),
			],
		] );
	}

	protected function tearDown() :void {
		$this->resetBuiltMetersCaches();
		parent::tearDown();
	}

	public function test_offcanvas_analysis_uses_supplied_meter_channel() :void {
		$action = new MeterAnalysis( [
			'meter'         => MeterSummary::SLUG,
			'meter_channel' => '  ConFig ',
		] );

		$components = $this->invokeNonPublicMethod( $action, 'getMeterComponents' );

		$this->assertSame( 88, (int)$components[ 'totals' ][ 'percentage' ] );
	}

	public function test_invalid_meter_channel_surfaces_strict_handler_rejection() :void {
		$action = new MeterAnalysis( [
			'meter'         => MeterSummary::SLUG,
			'meter_channel' => 'invalid-channel',
		] );
		$this->expectException( \InvalidArgumentException::class );
		$this->invokeNonPublicMethod( $action, 'getMeterComponents' );
	}
}
