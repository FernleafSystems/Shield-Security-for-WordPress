<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class RunTests {

	use PluginControllerConsumer;

	public function run() {
		array_map(
			fn( $test ) => $test->setCon( $this->getCon() )->run(), $this->enumPluginTests()
		);
		die( 'end test' );
	}

	/**
	 * @return PluginControllerConsumer[]
	 */
	private function enumPluginTests() :array {
		return [
			new VerifyEvents(),
			new VerifyUniqueEvents(),
		];
	}
}