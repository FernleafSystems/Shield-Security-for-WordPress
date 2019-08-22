<?php

use FernleafSystems\Wordpress\Services\Services;

/**
 * Class ICWP_WPSF_Processor_AuditTrail_Emails
 * @deprecated 8
 */
class ICWP_WPSF_Processor_AuditTrail_Emails extends ICWP_WPSF_AuditTrail_Auditor_Base {

	/**
	 */
	public function run() {
	}

	/**
	 * @param array $aEmail
	 * @return array
	 */
	public function auditEmailSend( $aEmail ) {
		return $aEmail;
	}

	/**
	 * @param array $aHeaders
	 * @return array
	 * @deprecated 8
	 */
	private function extractCcFromHeaders( $aHeaders ) {
		return [];
	}

	/**
	 * @return array
	 */
	private function findEmailSenderBacktrace() {
		return [];
	}
}