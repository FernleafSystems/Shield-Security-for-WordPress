<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;

use FernleafSystems\Wordpress\Services\Services;

class Posts extends Base {

	protected function run() {
		add_action( 'deleted_post', [ $this, 'auditDeletedPost' ] );
		add_action( 'transition_post_status', [ $this, 'auditPostStatus' ], 30, 3 );
	}

	/**
	 * @param string $postID
	 */
	public function auditDeletedPost( $postID ) {
		$post = Services::WpPost()->getById( $postID );
		if ( $post instanceof \WP_Post && !$this->isIgnoredPostType( $post ) ) {
			$this->con()->fireEvent(
				'post_deleted',
				[ 'audit_params' => [ 'title' => $post->post_title ] ]
			);
		}
	}

	/**
	 * @param string   $newStatus
	 * @param string   $oldStatus
	 * @param \WP_Post $post
	 */
	public function auditPostStatus( $newStatus, $oldStatus, $post ) {

		if ( !$post instanceof \WP_Post || $this->isIgnoredPostType( $post )
			 || in_array( $newStatus, [ 'auto-draft', 'inherit' ] ) ) {
			return;
		}

		if ( $newStatus == 'trash' ) {
			$event = 'post_trashed';
		}
		elseif ( $oldStatus == 'trash' ) {
			$event = 'post_recovered';
		}
		elseif ( in_array( $newStatus, [ 'publish', 'private' ] ) ) {

			if ( in_array( $oldStatus, [ 'publish', 'private' ] ) ) {
				$event = 'post_updated';
			}
			else {
				$event = 'post_published';
			}
		}
		elseif ( in_array( $oldStatus, [ 'publish', 'private' ] ) && $newStatus == 'draft' ) {
			$event = 'post_unpublished';
		}
		else {
			$event = 'post_updated';
		}

		$this->con()->fireEvent(
			$event,
			[
				'audit_params' => [
					'title' => $post->post_title,
					'type'  => $post->post_type,
				]
			]
		);
	}

	private function isIgnoredPostType( \WP_Post $post ) :bool {
		return
			( $post->post_status == 'auto-draft' )
			||
			in_array(
				$post->post_type,
				[
					'revision',
					'nav_menu_item',
					'attachment'
				]
			);
	}
}