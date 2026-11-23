<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase {

	protected DebugLogger $logger;

	protected function setUp() :void {
		parent::setUp();
		$this->logger = new DebugLogger();
		$this->logger->info( sprintf( 'Starting test: %s::%s', static::class, $this->getName() ) );
	}

	protected function tearDown() :void {
		$this->logger->info( sprintf( 'Completed test: %s::%s', static::class, $this->getName() ) );
		parent::tearDown();
	}
}