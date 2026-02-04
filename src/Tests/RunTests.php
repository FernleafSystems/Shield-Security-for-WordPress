<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class RunTests {

	use PluginControllerConsumer;

	public function run() {
		\array_map(
			fn( $test ) => ( new $test() )->enroll(), $this->enumPluginTests()
		);
	}

	/**
	 * @return PluginControllerConsumer[]
	 */
	private function enumPluginTests() :array {
		return [
			VerifyConfig::class,
			VerifyActions::class,
			VerifyEvents::class,
			VerifyStrings::class,
			VerifyMeterComponents::class,
		];
	}
}