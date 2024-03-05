<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Report\Changes;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\LogRecord;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseZoneReportPosts extends BaseZoneReport {

	abstract protected function loadLogsFilterPostType() :string;

	protected function loadLogs() :array {
		$logs = parent::loadLogs();
		return \array_filter(
			$logs,
			function ( $log ) {
				return ( $log->meta_data[ 'type' ] ?? 'post' ) === $this->loadLogsFilterPostType();
			}
		);
	}

	protected function buildSummaryForLog( LogRecord $log ) :string {
		switch ( $log->event_slug ) {
			case 'post_created':
				$text = __( 'Created', 'wp-simple-firewall' );
				break;
			case 'post_updated':
				$text = __( 'Updated', 'wp-simple-firewall' );
				break;
			case 'post_published':
				$text = __( 'Published', 'wp-simple-firewall' );
				break;
			case 'post_unpublished':
				$text = __( 'Unpublished', 'wp-simple-firewall' );
				break;
			case 'post_deleted':
				$text = __( 'Permanently Deleted', 'wp-simple-firewall' );
				break;
			case 'post_trashed':
				$text = __( 'Trashed', 'wp-simple-firewall' );
				break;
			case 'post_recovered':
				$text = __( 'Recovered from trash', 'wp-simple-firewall' );
				break;
			case 'post_updated_title':
				$text = sprintf( __( 'Title Updated: %s&rarr;%s', 'wp-simple-firewall' ),
					sprintf( '<code>%s</code>', $log->meta_data[ 'title_old' ] ),
					sprintf( '<code>%s</code>', $log->meta_data[ 'title_new' ] )
				);
				break;
			case 'post_updated_content':
				$text = __( 'Content Updated', 'wp-simple-firewall' );
				break;
			case 'post_updated_slug':
				$text = sprintf( __( 'Slug Updated: %s&rarr;%s', 'wp-simple-firewall' ),
					sprintf( '<code>%s</code>', $log->meta_data[ 'slug_old' ] ),
					sprintf( '<code>%s</code>', $log->meta_data[ 'slug_new' ] )
				);
				break;
			default:
				$text = parent::buildSummaryForLog( $log );
				break;
		}
		return $text;
	}

	protected function getLinkForLog( LogRecord $log ) :array {
		$postID = $log->meta_data[ 'post_id' ] ?? null;
		if ( empty( $postID ) || empty( get_post( $postID ) ) ) {
			$link = [
				'href' => Services::WpGeneral()->getAdminUrl( 'edit.php' ),
				'text' => $this->getZoneName(),
			];
		}
		else {
			$link = [
				'href' => get_edit_post_link( $postID ),
				'text' => __( 'Edit', 'wp-simple-firewall' ),
			];
		}
		return $link;
	}

	protected function getNameForLog( LogRecord $log ) :string {
		$postID = $log->meta_data[ 'post_id' ];
		$post = empty( $postID ) ? null : get_post( $postID );
		return \is_null( $post ) ? ( $log->meta_data[ 'title' ] ?? __( 'Unknown', 'wp-simple-firewall' ) ) : $post->post_title;
	}

	protected function getUniqFromLog( LogRecord $log ) :string {
		return $log->meta_data[ 'post_id' ] ?? ( $log->meta_data[ 'title' ] ?? (string)$log->id );
	}

	protected function getLoadLogsWheres() :array {
		return [
			sprintf( "`log`.`event_slug` IN ('%s')", \implode( "','", [
				'post_created',
				'post_updated',
				'post_published',
				'post_unpublished',
				'post_deleted',
				'post_trashed',
				'post_recovered',
				'post_updated_title',
				'post_updated_content',
				'post_updated_slug',
			] ) ),
		];
	}
}