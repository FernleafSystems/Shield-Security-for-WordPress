<?php

class ICWP_WPSF_Processor_AuditTrail_Emails extends ICWP_WPSF_AuditTrail_Auditor_Base {

	/**
	 */
	public function run() {
		add_filter( 'wp_mail', array( $this, 'auditEmailSend' ) );
	}

	/**
	 * @param array $aEmail
	 * @return array
	 */
	public function auditEmailSend( $aEmail ) {

		if ( is_array( $aEmail ) ) {
			$sTo = isset( $aEmail[ 'to' ] ) ? $aEmail[ 'to' ] : 'no email address provided';
			if ( is_array( $sTo ) ) {
				$sTo = implode( ', ', $sTo );
			}

			$aBacktrace = $this->findEmailSenderBacktrace();

			$aMsg = array(
				sprintf( _wpsf__( 'There was an attempt to send an email using the "%s" function.' ), 'wp_mail' ),
				sprintf( _wpsf__( 'It was sent to "%s" with the subject "%s".' ), $sTo, $aEmail[ 'subject' ] ),
			);
			if ( !empty( $aBacktrace ) ) {
				$aMsg[] = sprintf( _wpsf__( 'The "%s" function was called from the file "%s" on line %s.' ),
					'wp_mail',
					$aBacktrace[ 'file' ],
					$aBacktrace[ 'line' ]
				);
			}
		}
		else {
			$aMsg = array( sprintf( _wpsf__( 'Attempting to log email, but data was not of the correct type (%s)' ), 'array' ) );
		}

		$this->add( 'emails', 'email_attempt_send', 1, implode( " ", $aMsg ) );

		return $aEmail;
	}

	/**
	 * @return array
	 */
	private function findEmailSenderBacktrace() {
		$aBT = array();
		foreach ( debug_backtrace( false ) as $aItem ) {
			if ( isset( $aItem[ 'function' ] ) && 'wp_mail' === strtolower( $aItem[ 'function' ] ) ) {
				$aBT = $aItem;
				break;
			}
		}
		return $aBT;
	}
}