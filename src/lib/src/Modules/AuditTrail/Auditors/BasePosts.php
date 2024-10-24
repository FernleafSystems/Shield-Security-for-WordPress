<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\DiffVO;
use FernleafSystems\Wordpress\Services\Services;

abstract class BasePosts extends Base {

	protected function initAuditHooks() :void {
		add_action( 'post_updated', [ $this, 'auditPostUpdated' ], \PHP_INT_MAX, 3 );
		add_action( 'deleted_post', [ $this, 'auditDeletedPost' ] );
		add_action( 'transition_post_status', [ $this, 'auditPostStatus' ], 30, 3 );
	}

	/**
	 * @param int|mixed      $postID
	 * @param \WP_Post|mixed $post
	 * @param \WP_Post|mixed $postOld
	 */
	public function auditPostUpdated( $postID, $post, $postOld ) :void {
		if ( $post instanceof \WP_Post
			 && $this->isAllowablePostType( $post )
			 && !$this->isIgnoredPostType( $post )
		) {
			if ( !empty( $postOld->post_name ) && $post->post_name !== $postOld->post_name ) {
				$this->fireAuditEvent( 'post_updated_slug', [
					'post_id'  => $post->ID,
					'type'     => $post->post_type,
					'slug_old' => $postOld->post_name,
					'slug_new' => $post->post_name,
				] );
			}
			if ( !empty( $postOld->post_content ) && $post->post_content !== $postOld->post_content ) {
				$this->fireAuditEvent( 'post_updated_content', [
					'post_id' => $post->ID,
					'type'    => $post->post_type,
				] );
			}
			if ( !empty( $postOld->post_title ) && $post->post_title !== $postOld->post_title ) {
				$this->fireAuditEvent( 'post_updated_title', [
					'post_id'   => $post->ID,
					'type'      => $post->post_type,
					'title_old' => $postOld->post_title,
					'title_new' => $post->post_title,
				] );
			}

			$this->updateSnapshotItem( $post );
		}
	}

	/**
	 * @param string $postID
	 */
	public function auditDeletedPost( $postID ) {
		$post = Services::WpPost()->getById( $postID );
		if ( $post instanceof \WP_Post
			 && $this->isAllowablePostType( $post )
			 && !$this->isIgnoredPostType( $post )
		) {
			$this->fireAuditEvent( 'post_deleted', [
				'title'   => $post->post_title,
				'post_id' => $postID,
			] );
			$this->removeSnapshotItem( $post );
		}
	}

	/**
	 * @param string   $newStatus
	 * @param string   $oldStatus
	 * @param \WP_Post $post
	 */
	public function auditPostStatus( $newStatus, $oldStatus, $post ) {

		if ( !$post instanceof \WP_Post
			 || $this->isIgnoredPostType( $post )
			 || !$this->isAllowablePostType( $post )
			 || $oldStatus === $newStatus
			 || \in_array( $newStatus, [ 'auto-draft', 'inherit' ] ) ) {
			return;
		}

		if ( $newStatus == 'trash' ) {
			$event = 'post_trashed';
		}
		elseif ( $oldStatus == 'trash' ) {
			$event = 'post_recovered';
		}
		elseif ( \in_array( $newStatus, [ 'publish', 'private' ] ) ) {

			if ( \in_array( $oldStatus, [ 'publish', 'private' ] ) ) {
				$event = 'post_updated';
			}
			else {
				$event = 'post_published';
			}
		}
		elseif ( $oldStatus === 'auto-draft' ) {
			$event = 'post_created';
		}
		elseif ( \in_array( $oldStatus, [ 'publish', 'private' ] ) && $newStatus == 'draft' ) {
			$event = 'post_unpublished';
		}
		else {
			$event = null; // ?
		}

		if ( !empty( $event ) ) {
			$this->fireAuditEvent( $event, [
				'title' => $post->post_title,
				'type'  => $post->post_type,
			] );
			$this->updateSnapshotItem( $post );
		}
	}

	protected function isAllowablePostType( \WP_Post $post ) :bool {
		return false;
	}

	private function isIgnoredPostType( \WP_Post $post ) :bool {
		return \in_array( $post->post_type, [
			'revision',
			'nav_menu_item',
			'attachment'
		] );
	}

	/**
	 * @snapshotDiffCron
	 */
	public function snapshotDiffForPosts( DiffVO $diff ) {

		foreach ( $diff->added as $added ) {
			$post = get_post( $added[ 'uniq' ] );
			if ( $post instanceof \WP_Post ) {
				$this->auditPostStatus( 'auto-draft', $post->post_status, $post );
			}
		}

		foreach ( $diff->removed as $removed ) {
			$post = get_post( $removed[ 'uniq' ] );
			if ( $post instanceof \WP_Post ) {
				$this->auditPostStatus( $removed[ 'status' ], $post->post_status, $post );
			}
			else {
				$this->fireAuditEvent( 'post_deleted', [
					'title'   => $removed[ 'title' ],
					'post_id' => $removed[ 'uniq' ],
				] );
			}
		}

		foreach ( $diff->changed as $changed ) {
			$old = $changed[ 'old' ];
			$new = $changed[ 'new' ];
			$post = get_post( $old[ 'uniq' ] );
			if ( $post instanceof \WP_Post ) {
				if ( $old[ 'slug' ] !== $new[ 'slug' ] ) {
					$this->fireAuditEvent( 'post_updated_slug', [
						'post_id'  => $post->ID,
						'type'     => $post->post_type,
						'slug_old' => $old[ 'slug' ],
						'slug_new' => $new[ 'slug' ],
					] );
				}
				if ( $old[ 'hash_content' ] !== $new[ 'hash_content' ] ) {
					$this->fireAuditEvent( 'post_updated_content', [
						'post_id' => $post->ID,
						'type'    => $post->post_type,
					] );
				}
				if ( $old[ 'title' ] !== $new[ 'title' ] ) {
					$this->fireAuditEvent( 'post_updated_title', [
						'post_id'   => $post->ID,
						'type'      => $post->post_type,
						'title_old' => $old[ 'title' ],
						'title_new' => $new[ 'title' ],
					] );
				}
			}
		}
	}
}