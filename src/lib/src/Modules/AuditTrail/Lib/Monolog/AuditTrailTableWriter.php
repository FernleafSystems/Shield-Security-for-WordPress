<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Monolog;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\Logs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\Meta;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;
use Monolog\Handler\AbstractProcessingHandler;

class AuditTrailTableWriter extends AbstractProcessingHandler {

	use ModConsumer;

	/**
	 * @inheritDoc
	 */
	protected function write( array $record ) {
		$this->commitAudit( $record[ 'context' ] );
	}

	public function commitAudit( array $logData ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		try {
			$log = $this->createPrimaryLogRecord( $logData );
			$metas = array_merge( $logData[ 'audit' ] ?? [], $this->buildCommonMeta() );
			$metaRecord = new Meta\Ops\Record();
			$metaRecord->log_ref = $log->id;
			foreach ( $metas as $metaKey => $metaValue ) {
				$metaRecord->meta_key = $metaKey;
				$metaRecord->meta_value = $metaValue;
				$mod->getDbH_Meta()
					->getQueryInserter()
					->insert( $metaRecord );
			}
		}
		catch ( \Exception $e ) {
		}
	}

	private function buildCommonMeta() {
		$WP = Services::WpGeneral();
		$WPU = Services::WpUsers();

		$uid = $WPU->getCurrentWpUserId();
		if ( empty( $uid ) ) {
			if ( $WP->isCron() ) {
				$uid = 'cron';
			}
			elseif ( $WP->isWpCli() ) {
				$uid = 'wpcli';
			}
			else {
				$uid = '-';
			}
		}

		return array_filter( [
			'u_id'    => $uid,
			'u_roles' => is_numeric( $uid ) ? $WPU->getCurrentWpUser()->roles : '',
			'ip'      => (string)Services::IP()->getRequestIp(),
			'ua'      => Services::Request()->getUserAgent(),
			'count'   => 1,
			'rid'     => $this->getCon()->getShortRequestId(),
		] );
	}

	/**
	 * @param array $logData
	 * @return Logs\Ops\Record
	 * @throws \Exception
	 */
	protected function findPrimaryLogRecord( array $logData ) :Logs\Ops\Record {
		$latest = null;
		$canCount = in_array( $logData->event, $this->getCanCountEvents() );
		if ( $canCount ) {
			/** @var AuditTrail\Select $select */
			$select = $this->getDbHandler()->getQuerySelector();
			$latest = $select->filterByEvent( $logData->event )
							 ->filterByIp( $logData->ip )
							 ->filterByCreatedAt( Services::Request()->carbon()->subDay()->timestamp, '>' )
							 ->first();
			$canCount = ( $latest instanceof Logs\Ops\Record )
						&& ( $latest->ip === $logData->ip );
		}
	}

	/**
	 * @param array $logData
	 * @return Logs\Ops\Record
	 * @throws \Exception
	 */
	protected function createPrimaryLogRecord( array $logData ) :Logs\Ops\Record {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$record = new Logs\Ops\Record();
		$record->site_id = get_current_blog_id();
		$record->event_slug = $logData[ 'event_slug' ];
		$record->event_id = rand();

		$success = $mod->getDbH_Logs()
					   ->getQueryInserter()
					   ->insert( $record );
		if ( !$success ) {
			throw new \Exception( 'Failed to insert' );
		}

		/** @var Logs\Ops\Record $log */
		$log = $mod->getDbH_Logs()
				   ->getQuerySelector()
				   ->byId( Services::WpDb()->getVar( 'SELECT LAST_INSERT_ID()' ) );
		if ( empty( $log ) ) {
			throw new \Exception( 'Could not load log record' );
		}
		return $log;
	}

	/**
	 * TODO: This should be a config
	 * @return string[]
	 */
	private function getCanCountEvents() :array {
		return [ 'conn_kill' ];
	}
}
