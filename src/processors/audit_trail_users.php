<?php

/**
 * Class ICWP_WPSF_Processor_AuditTrail_Users
 * @deprecated
 */
class ICWP_WPSF_Processor_AuditTrail_Users extends ICWP_WPSF_AuditTrail_Auditor_Base {

	/**
	 */
	public function run() {
	}

	/**
	 * @param string $sUsername
	 * @deprecated 8
	 */
	public function auditUserLoginSuccess( $sUsername ) {
	}

	/**
	 * @param int $nUserId
	 * @deprecated 8
	 */
	public function auditNewUserRegistered( $nUserId ) {
	}

	/**
	 * @param int $nUserId
	 * @param int $nReassigned
	 * @deprecated 8
	 */
	public function auditDeleteUser( $nUserId, $nReassigned ) {
	}
}