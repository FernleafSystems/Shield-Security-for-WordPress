<?php

/**
 * Class ICWP_WPSF_Processor_AuditTrail_Posts
 * @deprecated 8
 */
class ICWP_WPSF_Processor_AuditTrail_Posts extends ICWP_WPSF_AuditTrail_Auditor_Base {

	/**
	 */
	public function run() {
	}

	/**
	 * @param string $nPostId
	 * @deprecated 8
	 */
	public function auditDeletedPost( $nPostId ) {
	}

	/**
	 * @param string  $sNewStatus
	 * @param string  $sOldStatus
	 * @param \WP_Post $oPost
	 * @deprecated 8
	 */
	public function auditPostStatus( $sNewStatus, $sOldStatus, $oPost ) {
	}

	/**
	 * @param WP_Post $oPost
	 * @return bool
	 * @deprecated 8
	 */
	protected function isIgnoredPostType( $oPost ) {
		return true;
	}
}