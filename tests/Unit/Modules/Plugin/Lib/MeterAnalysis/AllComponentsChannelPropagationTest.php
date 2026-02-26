<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin\Lib\MeterAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\{
	Component\AllComponents,
	Component\Base as ComponentBase,
	Meter\MeterOverallConfig
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\MeterAnalysisBuiltMetersCacheTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class AllComponentsChannelPropagationTest extends BaseUnitTest {

	use MeterAnalysisBuiltMetersCacheTrait;

	protected function setUp() :void {
		parent::setUp();
		$this->setBuiltMetersCache( [
			MeterOverallConfig::SLUG => $this->buildMeterFixture( 50, 'Overall' ),
		] );
		$this->setBuiltMetersByChannelCache( [
			MeterOverallConfig::SLUG => [
				ComponentBase::CHANNEL_CONFIG => $this->buildMeterFixture( 90, 'Overall' ),
				ComponentBase::CHANNEL_ACTION => $this->buildMeterFixture( 10, 'Overall' ),
			],
		] );
	}

	protected function tearDown() :void {
		$this->resetBuiltMetersCaches();
		parent::tearDown();
	}

	public function test_score_uses_channel_specific_overall_config() :void {
		$component = new AllComponents();

		$this->setComponentMeterChannel( $component, ComponentBase::CHANNEL_CONFIG );
		$this->assertSame( (int)\round( 90*AllComponents::WEIGHT/100 ), $component->score() );

		$this->setComponentMeterChannel( $component, ComponentBase::CHANNEL_ACTION );
		$this->assertSame( (int)\round( 10*AllComponents::WEIGHT/100 ), $component->score() );
	}

	public function test_score_defaults_to_combined_when_channel_missing() :void {
		$component = new AllComponents();
		$this->setComponentMeterChannel( $component, null );
		$this->assertSame( (int)\round( 50*AllComponents::WEIGHT/100 ), $component->score() );
	}

	private function setComponentMeterChannel( AllComponents $component, ?string $channel ) :void {
		$ref = new \ReflectionClass( ComponentBase::class );
		$prop = $ref->getProperty( 'meterChannel' );
		$prop->setAccessible( true );
		$prop->setValue( $component, $channel );
	}
}
