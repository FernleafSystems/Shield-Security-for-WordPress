<?php

if ( class_exists( 'ICWP_WPSF_Processor_AuditTrail_Emails' ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/audit_trail_auditor_base.php' );

class ICWP_WPSF_Processor_AuditTrail_Emails extends ICWP_WPSF_AuditTrail_Auditor_Base {

	/**
	 */
	public function run() {
		add_filter( 'wp_mail', array( $this, 'auditEmailSend' ) );
	}

	/**
	 * @param array $aEmailParams
	 * @return array
	 */
	public function auditEmailSend( $aEmailParams ) {

		if ( is_array( $aEmailParams ) ) {
			$sTo = isset( $aEmailParams[ 'to' ] ) ? $aEmailParams[ 'to' ] : 'no email address provided';
			if ( is_array( $sTo ) ) {
				$sTo = implode( ', ', $sTo );
			}
			$sMessage = sprintf( _wpsf__( 'There was an attempt to send an email using the "%s" function.' ), 'wp_mail' )
						.' '.sprintf( _wpsf__( 'It was sent to "%s" with the subject "%s".' ), $sTo, $aEmailParams[ 'subject' ] );
		}
		else {
			$sMessage = sprintf( _wpsf__( 'Attempting to log email, but data was not of the correct type (%s)' ), 'array' );
		}

		$this->add( 'emails', 'email_attempt_send', 1, $sMessage );

		return $aEmailParams;
	}
}