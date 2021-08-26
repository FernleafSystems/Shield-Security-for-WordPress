<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogTable;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\LoadLogs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\LogRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\AuditMessageBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Ops\ConvertLegacy;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class LoadRawTableData {

	use ModConsumer;

	/**
	 * @var LogRecord
	 */
	private $log;

	public function loadForLogs() :array {
		( new ConvertLegacy() )
			->setMod( $this->getMod() )
			->run();

		$srvEvents = $this->getCon()->loadEventsService();
		return array_values( array_map(
			function ( $log ) use ( $srvEvents ) {
				$this->log = $log;

				$data = $log->getRawData();
				$data[ 'ip' ] = $log->ip;
				$data[ 'event' ] = $srvEvents->getEventName( $log->event_slug );
				$data[ 'created_since' ] = Services::Request()
												   ->carbon( true )
												   ->setTimestamp( $log->created_at )
												   ->diffForHumans();
				$msg = AuditMessageBuilder::BuildFromLogRecord( $this->log );
				$data[ 'message' ] = sprintf( '<textarea readonly rows="%s">%s</textarea>',
					count( $msg ) + 1, sanitize_textarea_field( implode( "\n", $msg ) ) );
				$data[ 'user' ] = $this->getColumnContent_User();
				$data[ 'level' ] = $this->getColumnContent_Level();
				return $data;
			},
			$this->getLogRecords()
		) );
	}

	/**
	 * @return LogRecord[]
	 */
	private function getLogRecords() :array {
		return array_filter(
			( new LoadLogs() )
				->setMod( $this->getCon()->getModule_AuditTrail() )
				->run(),
			function ( $logRecord ) {
				return $this->getCon()->loadEventsService()->eventExists( $logRecord->event_slug );
			}
		);
	}

	private function getColumnContent_User() :string {
		$content = '-';
		$uid = $this->log->meta_data[ 'uid' ] ?? '';
		if ( !empty( $uid ) ) {
			if ( is_numeric( $uid ) ) {
				$user = Services::WpUsers()->getUserById( $uid );
				if ( $user instanceof \WP_User ) {
					$content = sprintf( '%s', $user->user_login );
				}
				else {
					$content = sprintf( 'User Unavailable (%s)', $uid );
				}
			}
			else {
				$content = $uid === 'cron' ? 'WP Cron' : 'WP-CLI';
			}
		}
		return $content;
	}

	private function getColumnContent_Level() :string {
		return __( ucfirst( $this->getCon()->loadEventsService()->getEventDef( $this->log->event_slug )[ 'level' ] ) );
	}
}