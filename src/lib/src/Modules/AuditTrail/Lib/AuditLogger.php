<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Dependencies\Monolog;
use FernleafSystems\Wordpress\Plugin\Shield\Logging\Processors;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\Logs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogHandlers\{
	LocalDbWriter,
	LogFileHandler
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Lib\EventsListener;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\FilterHandler;
use Monolog\Logger;

class AuditLogger extends EventsListener {

	/**
	 * @var array[]
	 */
	private $auditLogs = [];

	/**
	 * @var Logger
	 */
	private $logger;

	protected function init() {
		$con = $this->con();
		/** @var Options $opts */
		$opts = $con->getModule_AuditTrail()->getOptions();
		if ( $opts->isLogToDB() ) {
			// The Request Logger is required to link up the DB entries.
			$con->getModule_Traffic()->getRequestLogger()->execute();
		}
	}

	/**
	 * We initialise the loggers as late on as possible to prevent Monolog conflicts.
	 */
	protected function onShutdown() {
		if ( !$this->con()->plugin_deleting && $this->isMonologLibrarySupported() ) {
			$this->initLogger();
			foreach ( \array_reverse( $this->auditLogs ) as $auditLog ) {
				try {
					$this->getLogger()->log(
						$auditLog[ 'level' ] ?? $auditLog[ 'event_def' ][ 'level' ],
						ActivityLogMessageBuilder::Build( $auditLog[ 'event_slug' ], $auditLog[ 'audit_params' ] ?? [] ),
						$auditLog
					);
				}
				catch ( \InvalidArgumentException $e ) {
				}
			}
		}
	}

	protected function initLogger() {
		$con = $this->con();
		/** @var Options $opts */
		$opts = $con->getModule_AuditTrail()->getOptions();

		if ( $this->isMonologLibrarySupported() ) {

			if ( $opts->isLogToDB() ) {
				$this->getLogger()
					 ->pushHandler(
						 new FilterHandler( new LocalDbWriter(), $opts->getLogLevelsDB() )
					 );
			}

			$fileLogLevels = \method_exists( $this, 'getLogLevelsFile' ) ? $this->getLogLevelsFile() : $opts->getLogLevelsFile();
			if ( $con->cache_dir_handler->exists()
				 && !\in_array( 'disabled', $fileLogLevels ) && !empty( $opts->getLogFilePath() )
			) {
				try {
					$fileHandlerWithFilter = new FilterHandler( new LogFileHandler(), $fileLogLevels );
					if ( $opts->getOpt( 'log_format_file' ) === 'json' ) {
						$fileHandlerWithFilter->getHandler()->setFormatter( new JsonFormatter() );
					}
					$this->getLogger()->pushHandler( $fileHandlerWithFilter );
				}
				catch ( \Exception $e ) {
				}
			}

			$this->pushCustomHandlers();
		}
	}

	public function isMonologLibrarySupported() :bool {
		try {
			( new Monolog() )->assess();
			$supported = true;
		}
		catch ( \Exception $e ) {
			$supported = false;
		}
		return $supported;
	}

	private function pushCustomHandlers() {
		if ( $this->con()->caps->canActivityLogsSendToIntegrations() ) {
			$custom = apply_filters( 'shield/custom_audit_trail_handlers', [] );
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
			$this->logger = new Logger( 'audit', [], \array_map( function ( $class ) {
				return new $class();
			}, $this->enumMetaProcessors() ) );
		}
		return $this->logger;
	}

	protected function enumMetaProcessors() :array {
		return [
			Processors\RequestMetaProcessor::class,
			Processors\UserMetaProcessor::class,
			Processors\WpMetaProcessor::class,
		];
	}

	protected function captureEvent( string $evt, array $meta = [], array $def = [] ) {

		$meta = apply_filters( 'shield/audit_event_meta', $meta, $evt );

		if ( $def[ 'audit' ] && empty( $meta[ 'suppress_audit' ] ) ) {
			$meta[ 'event_slug' ] = $evt;
			$meta[ 'event_def' ] = $def;
			// cater for where certain events may happen more than once in the same request
			if ( !empty( $def[ 'audit_multiple' ] ) ) {
				$this->auditLogs[] = $meta;
			}
			else {
				$this->auditLogs[ $evt ] = $meta;
			}
		}
	}

	private function getLogLevelsFile() :array {
		/** @var Options $opts */
		$opts = $this->con()->getModule_AuditTrail()->getOptions();
		$levels = $opts->getOpt( 'log_level_file', [] );
		if ( empty( $levels ) ) {
			$opts->resetOptToDefault( 'log_level_file' );
		}
		elseif ( \count( $levels ) > 1 ) {
			if ( \in_array( 'disabled', $levels ) ) {
				$opts->setOpt( 'log_level_file', [ 'disabled' ] );
			}
			elseif ( \in_array( 'same_as_db', $levels ) ) {
				$opts->setOpt( 'log_level_file', [ 'same_as_db' ] );
			}
		}
		$levels = $opts->getOpt( 'log_level_file', [] );
		return \in_array( 'same_as_db', $levels ) ? $opts->getLogLevelsDB() : $levels;
	}
}