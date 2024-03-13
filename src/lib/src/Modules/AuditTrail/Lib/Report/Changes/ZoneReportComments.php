<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Report\Changes;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\LogRecord;
use FernleafSystems\Wordpress\Services\Services;

class ZoneReportComments extends BaseZoneReport {

	protected function buildSummaryForLog( LogRecord $log ) :string {
		switch ( $log->event_slug ) {
			case 'comment_created':
				$text = sprintf( __( 'Created with status %s', 'wp-simple-firewall' ),
					sprintf( '<code>%s</code>', $log->meta_data[ 'status' ] ) );
				break;
			case 'comment_status_updated':
				$text = sprintf( __( 'Status changed: %s&rarr;%s', 'wp-simple-firewall' ),
					sprintf( '<code>%s</code>', $log->meta_data[ 'status_old' ] ),
					sprintf( '<code>%s</code>', $log->meta_data[ 'status_new' ] )
				);
				break;
			case 'comment_deleted':
				$text = __( 'Deleted', 'wp-simple-firewall' );
				break;
			default:
				$text = parent::buildSummaryForLog( $log );
				break;
		}
		return $text;
	}

	public function getZoneName() :string {
		return __( 'Comments' );
	}

	protected function getLoadLogsWheres() :array {
		return [
			sprintf( "`log`.`event_slug` IN ('%s')", \implode( "','", [
				'comment_created',
				'comment_status_updated',
				'comment_deleted',
			] ) ),
		];
	}

	protected function getLinkForLog( LogRecord $log ) :array {
		$comment = get_comment( $log->meta_data[ 'comment_id' ] );
		if ( empty( $comment ) ) {
			$link = [
				'href' => Services::WpGeneral()->getAdminUrl( 'edit-comments.php' ),
				'text' => __( 'Comments' ),
			];
		}
		else {
			$link = [
				'href' => get_edit_comment_link( $log->meta_data[ 'comment_id' ] ),
				'text' => __( 'View Comment' ),
			];
		}
		return $link;
	}

	protected function getNameForLog( LogRecord $log ) :string {
		return sprintf( '%s ID:%s', __( 'Comment' ), $log->meta_data[ 'comment_id' ] );
	}

	protected function getUniqFromLog( LogRecord $log ) :string {
		return $log->meta_data[ 'comment_id' ];
	}
}