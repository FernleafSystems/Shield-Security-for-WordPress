<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\License;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib\Activate;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

/**
 * Unit tests for License Activate orchestrator class
 */
class ActivateTest extends BaseUnitTest {

	public function testClassExists() :void {
		$this->assertTrue( \class_exists( Activate::class ) );
	}

	public function testRunMethodExists() :void {
		$activate = new Activate();
		$this->assertTrue( \method_exists( $activate, 'run' ) );
	}

	public function testUsesPluginControllerConsumerTrait() :void {
		$reflection = new \ReflectionClass( Activate::class );
		$traits = $reflection->getTraitNames();
		$this->assertContains( PluginControllerConsumer::class, $traits );
	}
}
