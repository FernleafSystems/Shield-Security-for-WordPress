<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\Logs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogHandlers\LocalDbWriter;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogHandlers\LogFileHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Lib\EventsListener;
use Monolog\Formatter\JsonFormatter;
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

		$handlers = [];
		if ( $con->hasCacheDir() && $opts->isLogToFile() ) {
			try {
				$fileHandler = ( new LogFileHandler( $mod ) )->setLevel( $opts->getLogLevelFile() );
				if ( $opts->getOpt( 'log_format_file' ) === 'json' ) {
					$fileHandler->setFormatter( new JsonFormatter() );
				}
				$handlers[] = $fileHandler;
			}
			catch ( \Exception $e ) {
			}
		}

		$handlers[] = ( new LocalDbWriter() )
			->setMod( $mod )
			->setLevel( $opts->getLogLevelDB() );

		$this->logger = new Logger( 'shield', $handlers, [
			( new LogHandlers\Processors\RequestMetaProcessor() )->setCon( $con ),
			new LogHandlers\Processors\UserMetaProcessor(),
			new LogHandlers\Processors\WpMetaProcessor()
		] );
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
				$this->logger->log(
					$auditLog[ 'level' ] ?? $auditLog[ 'event_def' ][ 'level' ],
					AuditMessageBuilder::Build( $auditLog[ 'event_slug' ], $auditLog[ 'audit_params' ] ?? [] ),
					$auditLog
				);
			}
		}
	}
}