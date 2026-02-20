<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin\Lib\MeterAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\{
	Component\AllComponents,
	Component\Base as ComponentBase,
	Handler,
	Meter\MeterOverallConfig
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class AllComponentsChannelPropagationTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		$this->setCombinedCache( [
			MeterOverallConfig::SLUG => $this->meterFixture( 50 ),
		] );
		$this->setChannelCache( [
			MeterOverallConfig::SLUG => [
				ComponentBase::CHANNEL_CONFIG => $this->meterFixture( 90 ),
				ComponentBase::CHANNEL_ACTION => $this->meterFixture( 10 ),
			],
		] );
	}

	protected function tearDown() :void {
		$this->setCombinedCache( [] );
		$this->setChannelCache( [] );
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

	private function meterFixture( int $percentage ) :array {
		return [
			'title'       => 'Overall',
			'subtitle'    => 'Overall',
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

