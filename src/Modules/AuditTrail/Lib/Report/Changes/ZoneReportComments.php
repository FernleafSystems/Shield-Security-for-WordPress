<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Report\Changes;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\LogRecord;
use FernleafSystems\Wordpress\Services\Services;

class ZoneReportComments extends BaseZoneReport {

	protected function buildSummaryLinesForLog( LogRecord $log ) :array {
		switch ( $log->event_slug ) {
			case 'comment_created':
				$text = sprintf( __( 'Created with status %s', 'wp-simple-firewall' ),
					$log->meta_data[ 'status' ] ?? __( 'missing data', 'wp-simple-firewall' ) );
				break;
			case 'comment_status_updated':
				/* translators: %1$s: old status, %2$s: new status */
				$text = sprintf( __( 'Status changed: %1$s -> %2$s', 'wp-simple-firewall' ),
					$log->meta_data[ 'status_old' ] ?? __( 'missing data', 'wp-simple-firewall' ),
					$log->meta_data[ 'status_new' ] ?? __( 'missing data', 'wp-simple-firewall' )
				);
				break;
			case 'comment_deleted':
				$text = __( 'Deleted', 'wp-simple-firewall' );
				break;
			default:
				return parent::buildSummaryLinesForLog( $log );
		}
		return [ (string)$text ];
	}

	public function getZoneName() :string {
		return __( 'Comments', 'wp-simple-firewall' );
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
				'text' => __( 'Comments', 'wp-simple-firewall' ),
			];
		}
		else {
			$link = [
				'href' => get_edit_comment_link( $log->meta_data[ 'comment_id' ] ),
				'text' => __( 'View Comment', 'wp-simple-firewall' ),
			];
		}
		return $link;
	}

	protected function getNameForLog( LogRecord $log ) :string {
		return sprintf( '%s ID:%s', __( 'Comment', 'wp-simple-firewall' ), $log->meta_data[ 'comment_id' ] );
	}

	protected function getUniqFromLog( LogRecord $log ) :string {
		return $log->meta_data[ 'comment_id' ];
	}
}
