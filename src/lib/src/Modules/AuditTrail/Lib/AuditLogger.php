<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\Commit;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\Logs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Lib\EventsListener;
use Monolog\Handler\StreamHandler;
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
		$this->logger = new Logger( 'shield' );
		$this->logger->pushHandler(
			new StreamHandler( $this->getCon()->getPluginCachePath( '.shield.log' ), Logger::DEBUG )
		);
	}

	/**
	 * @param string $evt
	 * @param array  $meta
	 * @param array  $def
	 */
	protected function captureEvent( string $evt, $meta = [], $def = [] ) {

		$meta = apply_filters( 'shield/audit_event_meta', $meta, $evt );
		$this->logger->info( 'test' );
		if ( $def[ 'audit' ] && empty( $meta[ 'suppress_audit' ] ) ) {

			$meta[ 'event_slug' ] = $evt;
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
			( new Commit() )
				->setMod( $this->getCon()->getModule_AuditTrail() )
				->commitAudits( $this->auditLogs );
			$this->auditLogs = [];
		}
	}
}