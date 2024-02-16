<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib;

use AptowebDeps\Monolog\Logger;
use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Dependencies\Monolog;
use FernleafSystems\Wordpress\Plugin\Shield\Logging\Processors;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\ModConsumer;

class RequestLogger {

	use ExecOnce;
	use ModConsumer;

	/**
	 * @var Logger
	 */
	private $logger;

	private $hasLogged = false;

	private $isDependentLog = false;

	protected function canRun() :bool {
		return $this->opts()->isOpt( 'enable_traffic', 'Y' ) &&
			   !self::con()->this_req->wp_is_wpcli
			   && self::con()->db_con->dbhReqLogs()->isReady();
	}

	/**
	 * We initialise the loggers as late on as possible to prevent Monolog conflicts.
	 */
	protected function run() {
		add_action( self::con()->prefix( 'plugin_shutdown' ), function () {
			if ( $this->isLogged() ) {
				$this->createLog();
			}
		}, 1000 ); // high enough to come after audit trail
	}

	public function isLogged() :bool {
		return !self::con()->plugin_deleting && apply_filters( 'shield/is_log_traffic', false );
	}

	public function createDependentLog() :void {
		$this->isDependentLog = true;
		$this->createLog();
	}

	private function createLog() {
		if ( !$this->hasLogged ) {
			try {
				( new Monolog() )->assess();
				$this->initLogger();
				$this->getLogger()->log( 'debug', 'log request' );
			}
			catch ( \Exception $e ) {
				error_log( $e->getMessage() );
			}
			finally {
				$this->hasLogged = true;
			}
		}
	}

	private function initLogger() {
		$this->getLogger()->pushHandler( new LogHandlers\LocalDbWriter() );
		$this->pushCustomHandlers();
	}

	public function isDependentLog() :bool {
		return $this->isDependentLog;
	}

	private function pushCustomHandlers() {
		if ( self::con()->caps->canActivityLogsSendToIntegrations() ) {
			$custom = apply_filters( 'shield/custom_request_log_handlers', [] );
			\array_map(
				function ( $handler ) {
					$this->getLogger()->pushHandler( $handler );
				},
				\is_array( $custom ) ? $custom : []
			);
		}
	}

	public function getLogger() :Logger {
		if ( !isset( $this->logger ) ) {
			$this->logger = new Logger( 'request', [], \array_map( function ( $processorClass ) {
				return new $processorClass();
			}, $this->enumMetaProcessors() ) );
		}
		return $this->logger;
	}

	protected function enumMetaProcessors() :array {
		return [
			Processors\ShieldMetaProcessor::class,
			Processors\RequestMetaProcessor::class,
			Processors\UserMetaProcessor::class,
			Processors\WpMetaProcessor::class,
		];
	}
}