<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\LogHandlers\LocalDbWriter;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\RequestLogger;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use Monolog\Handler\TestHandler;

class RequestLoggerCustomHandlersIntegrationTest extends ShieldIntegrationTestCase {

	public function test_request_logger_pushes_only_valid_custom_handlers_in_current_stack_order() :void {
		$this->enablePremiumCapabilities( [ 'activity_logs_send_to_integrations' ] );
		$first = new TestHandler();
		$second = new TestHandler();
		$callback = static fn() :array => [ $first, new \stdClass(), $second ];
		\add_filter( 'shield/custom_request_log_handlers', $callback, \PHP_INT_MAX );

		try {
			$logger = new RequestLogger();
			$method = new \ReflectionMethod( $logger, 'initLogger' );
			$method->setAccessible( true );
			$method->invoke( $logger );
			$handlers = $logger->getLogger()->getHandlers();
			$this->assertSame( $second, $handlers[ 0 ] );
			$this->assertSame( $first, $handlers[ 1 ] );
			$this->assertInstanceOf( LocalDbWriter::class, $handlers[ 2 ] );
		}
		finally {
			\remove_filter( 'shield/custom_request_log_handlers', $callback, \PHP_INT_MAX );
		}
	}
}
