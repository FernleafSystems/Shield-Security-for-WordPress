<?php

if ( class_exists( 'ICWP_WPSF_Processor_AuditTrail_Posts' ) ) {
	return;
}

require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'audit_trail_base_auditor.php' );

class ICWP_WPSF_Processor_AuditTrail_Posts extends ICWP_WPSF_AuditTrail_Auditor_Base {

	/**
	 */
	public function run() {
		add_action( 'deleted_post', array( $this, 'auditDeletedPost' ) );
		add_action( 'transition_post_status', array( $this, 'auditPostStatus' ), 30, 3 );
	}

	/**
	 * @param string $nPostId
	 */
	public function auditDeletedPost( $nPostId ) {

		$oPost = get_post( $nPostId );
		if ( ( $oPost instanceof WP_Post ) && ( !$this->getIsIgnoredPostType( $oPost ) ) ) {
			$this->add( 'posts', 'post_deleted', 2,
				sprintf( _wpsf__( 'WordPress Post entitled "%s" was permanently deleted from trash.' ), $oPost->post_title )
			);
		}
	}

	/**
	 * @param string  $sNewStatus
	 * @param string  $sOldStatus
	 * @param WP_Post $oPost
	 */
	public function auditPostStatus( $sNewStatus, $sOldStatus, $oPost ) {

		if ( !( $oPost instanceof WP_Post ) || ( $this->getIsIgnoredPostType( $oPost ) ) || in_array( $sNewStatus, array(
				'auto-draft',
				'inherit'
			) ) ) {
			return;
		}

		if ( $sNewStatus == 'trash' ) {
			$sEvent = 'post_trashed';
			$sHumanEvent = _wpsf__( 'moved to trash' );
		}
		else if ( $sOldStatus == 'trash' && $sNewStatus != 'trash' ) {
			$sEvent = 'post_recovered';
			$sHumanEvent = _wpsf__( 'recovered from trash' );
		}
		else if ( in_array( $sNewStatus, array( 'publish', 'private' ) ) ) {
			$sEvent = 'post_published';
			$sHumanEvent = _wpsf__( 'published' );
		}
		else if ( in_array( $sOldStatus, array( 'publish', 'private' ) ) && $sNewStatus == 'draft' ) {
			$sEvent = 'post_unpublished';
			$sHumanEvent = _wpsf__( 'unpublished' );
		}
		else {
			$sEvent = 'post_updated';
			$sHumanEvent = _wpsf__( 'updated' );
		}

		$this->add( 'posts', $sEvent, 1,
			sprintf( _wpsf__( 'Post entitled "%s" was %s.' ), $oPost->post_title, $sHumanEvent )
		);
	}

	/**
	 * @param WP_Post $oPost
	 * @return bool
	 */
	protected function getIsIgnoredPostType( $oPost ) {
		return
			( $oPost->post_status == 'auto-draft' )
			||
			in_array(
				$oPost->post_type,
				array(
					'revision',
					'nav_menu_item',
					'attachment'
				)
			);
	}
}