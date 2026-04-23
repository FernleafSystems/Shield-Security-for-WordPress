<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldWordPressTestCase;

class ModConSiteLoopbackIntegrationTest extends ShieldWordPressTestCase {

	public function testSiteHealthLoopbackSuccessRunsDirectly() :void {
		$requestCount = 0;
		$httpStub = function ( $preempt, array $args, string $url ) use ( &$requestCount ) {
			unset( $preempt, $args, $url );
			$requestCount++;
			return $this->httpResponse( 200 );
		};
		\add_filter( 'pre_http_request', $httpStub, 10, 3 );

		try {
			$this->assertTrue( $this->pluginModCon()->canSiteLoopback() );
			$this->assertSame( 1, $requestCount );
		}
		finally {
			\remove_filter( 'pre_http_request', $httpStub, 10 );
		}
	}

	public function testSiteHealthLoopbackFailureDoesNotFallBackToWpCronProbe() :void {
		$requestCount = 0;
		$httpStub = function ( $preempt, array $args, string $url ) use ( &$requestCount ) {
			unset( $preempt, $args, $url );
			$requestCount++;
			return $this->httpResponse( 500 );
		};
		\add_filter( 'pre_http_request', $httpStub, 10, 3 );

		try {
			$this->assertFalse( $this->pluginModCon()->canSiteLoopback() );
			$this->assertSame( 1, $requestCount );
		}
		finally {
			\remove_filter( 'pre_http_request', $httpStub, 10 );
		}
	}

	private function pluginModCon() :ModCon {
		$con = self::con();
		$this->assertNotNull( $con, 'Controller must be available.' );
		$this->assertInstanceOf( ModCon::class, $con->plugin );
		return $con->plugin;
	}

	private function httpResponse( int $code ) :array {
		return [
			'headers'  => [],
			'body'     => '',
			'response' => [
				'code'    => $code,
				'message' => $code === 200 ? 'OK' : 'Internal Server Error',
			],
			'cookies'  => [],
		];
	}
}
