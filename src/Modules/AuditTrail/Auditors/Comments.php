<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Report\Changes\ZoneReportComments;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\DiffVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Snapper\SnapComments;

class Comments extends Base {

	protected function initAuditHooks() :void {
		add_action( 'comment_post', [ $this, 'auditNew' ], \PHP_INT_MAX );
		add_action( 'deleted_comment', [ $this, 'auditDelete' ], \PHP_INT_MAX, 2 );
		add_action( 'transition_comment_status', [ $this, 'auditStatusUpdate' ], \PHP_INT_MAX, 3 );
	}

	public function auditNew( $commentID ) :void {
		$comment = get_comment( $commentID );
		if ( $comment instanceof \WP_Comment ) {

			$this->fireAuditEvent( 'comment_created', [
				'comment_id' => $commentID,
				'post_id'    => $comment->comment_post_ID,
				'status'     => $this->commentStatusAsText( $comment->comment_approved ),
			] );

			$this->updateSnapshotItem( $comment );
		}
	}

	/**
	 * @param int|mixed          $commentID
	 * @param ?\WP_Comment|mixed $comment
	 */
	public function auditDelete( $commentID, $comment = null ) :void {
		if ( $comment instanceof \WP_Comment ) {
			$this->fireAuditEvent( 'comment_deleted', [
				'comment_id' => $commentID,
				'post_id'    => $comment->comment_post_ID,
				'status'     => $this->commentStatusAsText( $comment->comment_approved ),
			] );

			$this->removeSnapshotItem( $comment );
		}
	}

	/**
	 * We don't audit for 'delete' since this is an exception in how status is handled
	 * internally within WordPress
	 *
	 * @param string|mixed      $newStatus
	 * @param string|mixed      $oldStatus
	 * @param \WP_Comment|mixed $comment
	 */
	public function auditStatusUpdate( $newStatus, $oldStatus, $comment ) :void {
		if ( $comment instanceof \WP_Comment && $newStatus !== 'delete' ) {
			$this->fireAuditEvent( 'comment_status_updated', [
				'comment_id' => $comment->comment_ID,
				'post_id'    => $comment->comment_post_ID,
				'status_old' => $this->commentStatusAsText( $oldStatus ),
				'status_new' => $this->commentStatusAsText( $newStatus ),
			] );

			$this->updateSnapshotItem( $comment );
		}
	}

	/**
	 * @snapshotDiffCron
	 */
	public function snapshotDiffForComments( DiffVO $diff ) {

		foreach ( $diff->added as $added ) {
			$this->auditNew( $added[ 'uniq' ] );
		}

		foreach ( $diff->removed as $removed ) {
			$this->fireAuditEvent( 'comment_deleted', [
				'comment_id' => $removed[ 'uniq' ],
				'post_id'    => $removed[ 'post_id' ],
				'status'     => $this->commentStatusAsText( $removed[ 'status' ] ),
			] );
		}

		foreach ( $diff->changed as $changed ) {
			$old = $changed[ 'old' ];
			$new = $changed[ 'new' ];
			$comment = get_comment( $old[ 'uniq' ] );
			if ( $comment instanceof \WP_Comment ) {
				if ( $old[ 'status' ] != $new[ 'status' ] ) {
					$this->auditStatusUpdate( $new[ 'status' ], $old[ 'status' ], $comment );
				}
			}
		}
	}

	private function commentStatusAsText( string $status ) :string {
		if ( \in_array( $status, [ 1, '1', 'approve' ], true ) ) {
			$status = 'approved';
		}
		elseif ( \in_array( $status, [ 0, '0', 'hold', 'pending' ], true ) ) {
			$status = 'pending';
		}
		return $status;
	}

	public function getReporter() :ZoneReportComments {
		return new ZoneReportComments();
	}

	public function getSnapper() :SnapComments {
		return new SnapComments();
	}
}