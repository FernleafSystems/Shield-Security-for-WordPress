<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Base class for unit tests using Brain Monkey
 */
abstract class BaseUnitTest extends TestCase {

	protected function setUp() :void {
		parent::setUp();
	}

	protected function tearDown() :void {
		parent::tearDown();
	}
}