<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib;

use AptowebDeps\Monolog\Handler\FilterHandler;
use AptowebDeps\Monolog\Logger;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Dependencies\Monolog;
use FernleafSystems\Wordpress\Plugin\Shield\Events\EventsListener;
use FernleafSystems\Wordpress\Plugin\Shield\Logging\Processors;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogHandlers\LocalDbWriter;

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
		if ( self::con()->comps->activity_log->isLogToDB() ) {
			// The Request Logger is required to link up the DB entries.
			self::con()->comps->requests_log->execute();
		}
	}

	/**
	 * We initialise the loggers as late on as possible to prevent Monolog conflicts.
	 */
	protected function onShutdown() {
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
		$con = self::con();
		$auditCon = $con->comps->activity_log;

		if ( $this->isMonologLibrarySupported() ) {

			if ( $auditCon->isLogToDB() ) {
				$this->getLogger()
					 ->pushHandler(
						 new FilterHandler( new LocalDbWriter(), $auditCon->getLogLevelsDB() )
					 );
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
		if ( self::con()->caps->canActivityLogsSendToIntegrations() ) {
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
}