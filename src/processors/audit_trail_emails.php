<?php

if ( class_exists( 'ICWP_WPSF_Processor_AuditTrail_Emails' ) ) {
	return;
}

require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'base_wpsf.php' );

class ICWP_WPSF_Processor_AuditTrail_Emails extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 */
	public function run() {
		if ( $this->getIsOption( 'enable_audit_context_emails', 'Y' ) ) {
			add_filter( 'wp_mail', array( $this, 'auditEmailSend' ) );
		}
	}

	/**
	 * @param array $aEmailParameters
	 * @return array
	 */
	public function auditEmailSend( $aEmailParameters ) {
		$sTo = isset( $aEmailParameters[ 'to' ] ) ? $aEmailParameters[ 'to' ] : 'no email provided';
		if ( is_array( $sTo ) ) {
			$sTo = implode( ', ', $sTo );
		}
		$this->getAuditTrailEntries()
			 ->add( 'emails', 'email_attempt_send', 1,
				 sprintf( _wpsf__( 'There was an attempt to send an email using the "%s" function.' ), 'wp_mail' )
				 .' '.sprintf( _wpsf__( 'It was sent to "%s" with the subject "%s".' ), $sTo, $aEmailParameters[ 'subject' ] )
			 );

		return $aEmailParameters;
	}

	/**
	 * @return ICWP_WPSF_AuditTrail_Entries
	 */
	protected function getAuditTrailEntries() {
		return ICWP_WPSF_AuditTrail_Entries::GetInstance();
	}
}