<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use Brain\Monkey;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Base class for unit tests using Brain Monkey
 */
abstract class BaseUnitTest extends TestCase {

	protected function setUp() :void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown() :void {
		Monkey\tearDown();
		parent::tearDown();
	}
}