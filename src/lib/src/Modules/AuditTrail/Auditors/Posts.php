<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;

use FernleafSystems\Wordpress\Services\Services;

class Posts extends Base {

	protected function run() {
		add_action( 'deleted_post', [ $this, 'auditDeletedPost' ] );
		add_action( 'transition_post_status', [ $this, 'auditPostStatus' ], 30, 3 );
	}

	/**
	 * @param string $nPostId
	 */
	public function auditDeletedPost( $nPostId ) {
		$oPost = Services::WpPost()->getById( $nPostId );
		if ( $oPost instanceof \WP_Post && !$this->isIgnoredPostType( $oPost ) ) {
			$this->getCon()->fireEvent(
				'post_deleted',
				[ 'audit' => [ 'title' => $oPost->post_title ] ]
			);
		}
	}

	/**
	 * @param string   $sNewStatus
	 * @param string   $sOldStatus
	 * @param \WP_Post $oPost
	 */
	public function auditPostStatus( $sNewStatus, $sOldStatus, $oPost ) {

		if ( !$oPost instanceof \WP_Post || $this->isIgnoredPostType( $oPost )
			 || in_array( $sNewStatus, [ 'auto-draft', 'inherit' ] ) ) {
			return;
		}

		if ( $sNewStatus == 'trash' ) {
			$sEvent = 'post_trashed';
		}
		elseif ( $sOldStatus == 'trash' && $sNewStatus != 'trash' ) {
			$sEvent = 'post_recovered';
		}
		elseif ( in_array( $sNewStatus, [ 'publish', 'private' ] ) ) {

			if ( in_array( $sOldStatus, [ 'publish', 'private' ] ) ) {
				$sEvent = 'post_updated';
			}
			else {
				$sEvent = 'post_published';
			}
		}
		elseif ( in_array( $sOldStatus, [ 'publish', 'private' ] ) && $sNewStatus == 'draft' ) {
			$sEvent = 'post_unpublished';
		}
		else {
			$sEvent = 'post_updated';
		}

		$this->getCon()->fireEvent(
			$sEvent,
			[
				'audit' => [
					'title' => $oPost->post_title,
					'type'  => $oPost->post_type,
				]
			]
		);
	}

	/**
	 * @param \WP_Post $oPost
	 * @return bool
	 */
	private function isIgnoredPostType( $oPost ) {
		return
			( $oPost->post_status == 'auto-draft' )
			||
			in_array(
				$oPost->post_type,
				[
					'revision',
					'nav_menu_item',
					'attachment'
				]
			);
	}
}