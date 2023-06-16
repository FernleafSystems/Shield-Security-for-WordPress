<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Report;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\LogRecord;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseZoneReportPosts extends BaseZoneReport {

	protected function getLinkForLog( LogRecord $log ) :array {
		if ( empty( get_post( $log[ 'uniq' ] ) ) ) {
			$link = [
				'href' => Services::WpGeneral()->getAdminUrl( 'edit.php' ),
				'text' => $this->getZoneName(),
			];
		}
		else {
			$link = [
				'href' => get_edit_post_link( $log[ 'uniq' ] ),
				'text' => __( 'Edit', 'wp-simple-firewall' ),
			];
		}
		return $link;
	}

	protected function getNameForLog( LogRecord $log ) :string {
		return $log[ 'title' ];
	}

	protected function processDiffAdded( array $item ) :array {
		return [
			\sprintf( 'Published (ID:%s)', $item[ 'uniq' ] ),
		];
	}

	protected function processDiffRemoved( array $item ) :array {
		return [
			\sprintf( 'Unpublished (ID:%s)', $item[ 'uniq' ] ),
		];
	}

	protected function processDiffChanged( array $old, array $new ) :array {
		$changes = [];
		if ( $old[ 'slug' ] !== $new[ 'slug' ] ) {
			$changes[] = sprintf(
				__( 'Slug changed from %s to %s', 'wp-simple-firewall' ),
				sprintf( '<code>%s</code>', $old[ 'slug' ] ),
				sprintf( '<code>%s</code>', $new[ 'slug' ] )
			);
		}
		if ( $old[ 'title' ] !== $new[ 'title' ] ) {
			$changes[] = sprintf(
				__( 'Title changed from %s to %s', 'wp-simple-firewall' ),
				sprintf( '<code>%s</code>', esc_html( $old[ 'slug' ] ) ),
				sprintf( '<code>%s</code>', esc_html( $new[ 'slug' ] ) )
			);
		}
		if ( $old[ 'hash_content' ] !== $new[ 'hash_content' ] ) {
			$changes[] = __( 'Content was modified.', 'wp-simple-firewall' );
		}
		return $changes;
	}
}