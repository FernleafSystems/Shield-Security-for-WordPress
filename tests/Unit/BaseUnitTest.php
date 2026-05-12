<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use Brain\Monkey;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\BrainMonkeyWordPressTestFunctions;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Base class for unit tests using Brain Monkey
 */
abstract class BaseUnitTest extends TestCase {

	use BrainMonkeyWordPressTestFunctions;

	protected function setUp() :void {
		parent::setUp();
		Monkey\setUp();
		$this->registerWordPressPersistenceFunctionMocks();
	}

	protected function tearDown() :void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
