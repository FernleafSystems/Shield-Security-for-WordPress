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

	protected function assertArrayHasKeys( array $keys, array $array, string $message = '' ) :void {
		foreach ( $keys as $key ) {
			$this->assertArrayHasKey( $key, $array, $message );
		}
	}

	protected function assertStringContainsStrings( array $needles, string $haystack, string $message = '' ) :void {
		foreach ( $needles as $needle ) {
			$this->assertStringContainsString( $needle, $haystack, $message );
		}
	}

	protected function logDebug( string $message ) :void {
		$this->logger->debug( $message );
	}
}