<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Logging\Processors;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\Logs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogHandlers\LocalDbWriter;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogHandlers\LogFileHandler;
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
		$con = $this->getCon();
		$mod = $con->getModule_AuditTrail();
		/** @var Options $opts */
		$opts = $mod->getOptions();

		if ( $opts->isLogToDB() ) {
			$this->getLogger()
				 ->pushHandler(
					 new FilterHandler( ( new LocalDbWriter() )->setMod( $mod ), $opts->getLogLevelsDB() )
				 );
			// The Request Logger is required to link up the DB entries.
			$con->getModule_Traffic()->getRequestLogger()->execute();
		}

		if ( $con->cache_dir_handler->exists() && $opts->isLogToFile() ) {
			try {
				$fileHandlerWithFilter = new FilterHandler( new LogFileHandler( $mod ), $opts->getLogLevelsFile() );
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

	private function pushCustomHandlers() {
		$custom = apply_filters( 'shield/custom_audit_trail_handlers', [] );
		array_map(
			function ( $handler ) {
				$this->getLogger()->pushHandler( $handler );
			},
			( $this->getCon()->isPremiumActive() && is_array( $custom ) ) ? $custom : []
		);
	}

	public function getLogger() :Logger {
		if ( !isset( $this->logger ) ) {
			$this->logger = new Logger( 'audit', [], array_map( function ( $class ) {
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

	protected function onShutdown() {
		if ( !$this->getCon()->plugin_deleting ) {
			foreach ( array_reverse( $this->auditLogs ) as $auditLog ) {
				$this->getLogger()->log(
					$auditLog[ 'level' ] ?? $auditLog[ 'event_def' ][ 'level' ],
					AuditMessageBuilder::Build( $auditLog[ 'event_slug' ], $auditLog[ 'audit_params' ] ?? [] ),
					$auditLog
				);
			}
		}
	}
}