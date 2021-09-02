<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogTable;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\LoadLogs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\LogRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\AuditMessageBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Ops\ConvertLegacy;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class LoadRawTableData {

	use ModConsumer;

	/**
	 * @var LogRecord
	 */
	private $log;

	public function loadForLogs() :array {
		( new Traffic\Lib\Ops\ConvertLegacy() )
			->setMod( $this->getMod() )
			->run();
		( new ConvertLegacy() )
			->setMod( $this->getMod() )
			->run();

		$srvEvents = $this->getCon()->loadEventsService();
		return array_values( array_map(
			function ( $log ) use ( $srvEvents ) {
				$this->log = $log;

				$data = $log->getRawData();

				$data[ 'ip' ] = $this->log->ip;
				$data[ 'rid' ] = $this->log->rid ?? __( 'Unknown', 'wp-simple-firewall' );
				$data[ 'ip_linked' ] = $this->getColumnContent_RequestDetails();
				$data[ 'event' ] = $srvEvents->getEventName( $log->event_slug );
				$data[ 'created_since' ] = $this->getColumnContent_Date();
				$data[ 'message' ] = $this->getColumnContent_Message();
				$data[ 'user' ] = $this->getColumnContent_User();
				$data[ 'level' ] = $this->getColumnContent_Level();
				$data[ 'severity' ] = $this->getColumnContent_SeverityIcon();
				$data[ 'meta' ] = $this->getColumnContent_Meta();
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

	private function getColumnContent_RequestDetails() :string {
		return sprintf( '<h6><a href="%s" target="_blank">%s</a></h6>',
			$this->getCon()->getModule_Insights()->getUrl_IpAnalysis( $this->log->ip ),
			$this->log->ip
		);
	}

	private function getColumnContent_Date() :string {
		return sprintf( '%s<br /><small>%s</small>',
			Services::Request()
					->carbon( true )
					->setTimestamp( $this->log->created_at )
					->diffForHumans(),
			Services::WpGeneral()->getTimeStringForDisplay( $this->log->created_at )
		);
	}

	private function getColumnContent_User() :string {
		$content = '-';
		$uid = $this->log->meta_data[ 'uid' ] ?? '';
		if ( !empty( $uid ) ) {
			if ( is_numeric( $uid ) ) {
				$user = Services::WpUsers()->getUserById( $uid );
				if ( !empty( $user ) ) {
					$content = sprintf( '<a href="%s" target="_blank">%s</a>',
						Services::WpUsers()->getAdminUrl_ProfileEdit( $user ),
						$user->user_login );
				}
				else {
					$content = sprintf( 'Unavailable (ID:%s)', $uid );
				}
			}
			else {
				$content = $uid === 'cron' ? 'WP Cron' : 'WP-CLI';
			}
		}
		return $content;
	}

	private function getColumnContent_Message() :string {
		$msg = AuditMessageBuilder::BuildFromLogRecord( $this->log );
		return sprintf( '<span class="message-header">%s</span><textarea readonly rows="%s">%s</textarea>',
			$this->getCon()->loadEventsService()->getEventName( $this->log->event_slug ),
			count( $msg ) + 1, sanitize_textarea_field( implode( "\n", $msg ) ) );
	}

	private function getColumnContent_Meta() :string {
		return sprintf(
			'<button type="button" class="btn  btn-link" '.
			'data-toggle="popover" data-placement="left" '.
			'data-customClass="audit-meta" '.
			'data-rid="%s">%s</button>', $this->log->rid,
			sprintf( '<span class="meta-icon">%s</span>',
				$this->getCon()->svgs->raw( 'bootstrap/tags.svg' )
			)
		);
	}

	private function getColumnContent_Level() :string {
		return $this->getCon()->loadEventsService()->getEventDef( $this->log->event_slug )[ 'level' ];
	}

	private function getColumnContent_SeverityIcon() :string {
		$level = $this->getColumnContent_Level();
		$levelDetails = [
							'alert'   => [
								'icon' => 'x-octagon',
							],
							'warning' => [
								'icon' => 'exclamation-triangle',
							],
							'info'    => [
								'icon' => 'info-circle',
							],
							'debug'   => [
								'icon' => 'question-diamond',
							],
						][ $level ];
		return sprintf( '<span class="severity-%s severity-icon">%s</span>', $level,
			$this->getCon()->svgs->raw( sprintf( 'bootstrap/%s.svg', $levelDetails[ 'icon' ] ) )
		);
	}
}