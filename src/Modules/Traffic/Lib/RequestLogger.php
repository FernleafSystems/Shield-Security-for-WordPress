<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib;

use AptowebDeps\Monolog\Logger;
use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Dependencies\Monolog;
use FernleafSystems\Wordpress\Plugin\Shield\Logging\Processors;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class RequestLogger {

	use ExecOnce;
	use PluginControllerConsumer;

	/**
	 * @var Logger
	 */
	private $logger;

	private $hasLogged = false;

	private $isDependentLog = false;

	protected function canRun() :bool {
		$con = self::con();
		return $con->comps->opts_lookup->enabledTrafficLogger()
			   && !$con->this_req->wp_is_wpcli
			   && $con->db_con->req_logs->isReady();
	}

	/**
	 * We initialise the loggers as late on as possible to prevent Monolog conflicts.
	 */
	protected function run() {
		// knocks-off the live logging is required.
		self::con()->comps->opts_lookup->getTrafficLiveLogTimeRemaining();

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

	public function getAutoCleanDays() :int {
		$con = self::con();
		$days = $con->opts->optGet( 'auto_clean' );
		if ( $days !== $con->caps->getMaxLogRetentionDays() ) {
			$days = (int)\min( $days, $con->caps->getMaxLogRetentionDays() );
			$con->opts->optSet( 'auto_clean', $days );
		}
		return $days;
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