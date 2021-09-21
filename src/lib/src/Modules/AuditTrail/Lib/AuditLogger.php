<?php

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

		if ( $con->hasCacheDir() && $opts->isLogToFile() ) {
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
	}

	public function getLogger() :Logger {
		if ( !isset( $this->logger ) ) {
			$this->logger = new Logger( 'audit', [], [
				new Processors\RequestMetaProcessor(),
				new Processors\UserMetaProcessor(),
				new Processors\WpMetaProcessor()
			] );
		}
		return $this->logger;
	}

	/**
	 * @param string $evt
	 * @param array  $meta
	 * @param array  $def
	 */
	protected function captureEvent( string $evt, $meta = [], $def = [] ) {

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
			foreach ( $this->auditLogs as $auditLog ) {
				$this->getLogger()->log(
					$auditLog[ 'level' ] ?? $auditLog[ 'event_def' ][ 'level' ],
					AuditMessageBuilder::Build( $auditLog[ 'event_slug' ], $auditLog[ 'audit_params' ] ?? [] ),
					$auditLog
				);
			}
		}
	}
}