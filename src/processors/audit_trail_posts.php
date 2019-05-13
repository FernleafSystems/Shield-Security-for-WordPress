<?php

class ICWP_WPSF_Processor_AuditTrail_Posts extends ICWP_WPSF_AuditTrail_Auditor_Base {

	/**
	 */
	public function run() {
		add_action( 'deleted_post', [ $this, 'auditDeletedPost' ] );
		add_action( 'transition_post_status', [ $this, 'auditPostStatus' ], 30, 3 );
	}

	/**
	 * @param string $nPostId
	 */
	public function auditDeletedPost( $nPostId ) {

		$oPost = get_post( $nPostId );
		if ( $oPost instanceof WP_Post && !$this->isIgnoredPostType( $oPost ) ) {
			$this->add( 'posts', 'post_deleted', 2,
				sprintf( __( 'WordPress Post entitled "%s" was permanently deleted from trash.', 'wp-simple-firewall' ), $oPost->post_title )
			);
		}
	}

	/**
	 * @param string  $sNewStatus
	 * @param string  $sOldStatus
	 * @param WP_Post $oPost
	 */
	public function auditPostStatus( $sNewStatus, $sOldStatus, $oPost ) {

		if ( !$oPost instanceof WP_Post || $this->isIgnoredPostType( $oPost )
			 || in_array( $sNewStatus, [ 'auto-draft', 'inherit' ] ) ) {
			return;
		}

		if ( $sNewStatus == 'trash' ) {
			$sEvent = 'post_trashed';
			$sHumanEvent = __( 'moved to trash', 'wp-simple-firewall' );
		}
		else if ( $sOldStatus == 'trash' && $sNewStatus != 'trash' ) {
			$sEvent = 'post_recovered';
			$sHumanEvent = __( 'recovered from trash', 'wp-simple-firewall' );
		}
		else if ( in_array( $sNewStatus, [ 'publish', 'private' ] ) ) {

			if ( in_array( $sOldStatus, [ 'publish', 'private' ] ) ) {
				$sEvent = 'post_updated';
				$sHumanEvent = __( 'updated', 'wp-simple-firewall' );
			}
			else {
				$sEvent = 'post_published';
				$sHumanEvent = __( 'published', 'wp-simple-firewall' );
			}
		}
		else if ( in_array( $sOldStatus, [ 'publish', 'private' ] ) && $sNewStatus == 'draft' ) {
			$sEvent = 'post_unpublished';
			$sHumanEvent = __( 'unpublished', 'wp-simple-firewall' );
		}
		else {
			$sEvent = 'post_updated';
			$sHumanEvent = __( 'updated', 'wp-simple-firewall' );
		}

		$aMsg = [
			sprintf( __( 'Post entitled "%s" was %s.', 'wp-simple-firewall' ), $oPost->post_title, $sHumanEvent ),
			sprintf( '%s: %s', __( 'Post Type', 'wp-simple-firewall' ), $oPost->post_type ),
		];

		$this->add( 'posts', $sEvent, 1, implode( " ", $aMsg ) );
	}

	/**
	 * @param WP_Post $oPost
	 * @return bool
	 */
	protected function isIgnoredPostType( $oPost ) {
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