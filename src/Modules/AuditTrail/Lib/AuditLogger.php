<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib;

use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\FilterHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Dependencies\Monolog;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\{
	ActivityLogs\Ops as ActivityLogsDB,
	ActivityLogsMeta\Ops as ActivityLogsMetaDB,
	IPs\IPRecords,
	IPs\Ops as IPsDB,
	ReqLogs\Ops as ReqLogsDB,
	ReqLogs\RequestRecords
};
use FernleafSystems\Wordpress\Plugin\Shield\Events\EventsListener;
use FernleafSystems\Wordpress\Plugin\Shield\Logging\Processors;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogHandlers\LocalDbWriter as ActivityLogDbWriter;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\{
	LogHandlers\LocalDbWriter as RequestLogDbWriter,
	RequestLogger,
	RequestLogRetentionPolicy,
	RequestLogSuppressor
};

class AuditLogger extends EventsListener {

	private array $auditLogs = [];

	private Logger $logger;

	protected function init() {
		$this->primeUpgradeSensitiveLoggingClasses();
		// The Request Logger is required to link up DB request references for activity entries.
		self::con()->comps->requests_log->execute();
	}

	/**
	 * We initialise the loggers as late on as possible to prevent Monolog conflicts.
	 */
	protected function onShutdown() {
		// TODO: consider self::con()->is_my_upgrade - potential to skip logging on our own upgrades.
		if ( !self::con()->plugin_deleting && $this->isMonologLibrarySupported() ) {
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
		if ( $this->isMonologLibrarySupported() ) {
			$this->getLogger()->pushHandler( new FilterHandler(
				new ActivityLogDbWriter(),
				( new ActivityLogRetentionPolicy() )->dbWriteLevels()
			) );
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
		if ( self::con()->caps->canActivityLogsSendToIntegrations() ) {
			$custom = apply_filters( 'shield/custom_audit_trail_handlers', [] );
			\array_map(
				fn( $handler ) => $this->getLogger()->pushHandler( $handler ),
				\is_array( $custom ) ? $custom : []
			);
		}
	}

	public function getLogger() :Logger {
		return $this->logger ??= new Logger( 'audit', [], \array_map( fn( $c ) => new $c(), $this->enumMetaProcessors() ) );
	}

	protected function enumMetaProcessors() :array {
		return [
			Processors\RequestMetaProcessor::class,
			Processors\UserMetaProcessor::class,
			Processors\WpMetaProcessor::class,
		];
	}

	private function primeUpgradeSensitiveLoggingClasses() :void {
		foreach ( $this->upgradeSensitiveLoggingClasses() as $class ) {
			\class_exists( $class );
		}
	}

	/**
	 * @return string[]
	 */
	private function upgradeSensitiveLoggingClasses() :array {
		return [
			Logger::class,
			AbstractProcessingHandler::class,
			FilterHandler::class,
			Monolog::class,
			ActivityLogMessageBuilder::class,
			ActivityLogRetentionPolicy::class,
			ActivityLogDbWriter::class,
			RequestLogDbWriter::class,
			RequestLogger::class,
			RequestLogRetentionPolicy::class,
			RequestLogSuppressor::class,
			Processors\ShieldMetaProcessor::class,
			Processors\RequestMetaProcessor::class,
			Processors\UserMetaProcessor::class,
			Processors\WpMetaProcessor::class,
			ActivityLogsDB\Insert::class,
			ActivityLogsDB\Record::class,
			ActivityLogsDB\Select::class,
			ActivityLogsMetaDB\Insert::class,
			ActivityLogsMetaDB\Record::class,
			IPRecords::class,
			IPsDB\Insert::class,
			IPsDB\Record::class,
			IPsDB\Select::class,
			RequestRecords::class,
			ReqLogsDB\Insert::class,
			ReqLogsDB\Record::class,
			ReqLogsDB\Select::class,
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
}
