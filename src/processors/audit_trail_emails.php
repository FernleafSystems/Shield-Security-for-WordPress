<?php

use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_AuditTrail_Emails extends ICWP_WPSF_AuditTrail_Auditor_Base {

	/**
	 */
	public function run() {
		add_filter( 'wp_mail', array( $this, 'auditEmailSend' ), PHP_INT_MAX );
	}

	/**
	 * @param array $aEmail
	 * @return array
	 */
	public function auditEmailSend( $aEmail ) {

		$aMsg = [];
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

			// Attempt to capture BCC/CC
			if ( !empty( $aEmail[ 'headers' ] ) ) {
				$aHeaders = $aEmail[ 'headers' ];
				if ( is_string( $aHeaders ) ) {
					$aHeaders = explode( "\n", $aHeaders );
				}
				if ( is_array( $aHeaders ) ) {
					$aCCs = $this->extractCcFromHeaders( $aHeaders );
					if ( !empty( $aCCs[ 'bcc' ] ) ) {
						$aMsg[] = sprintf( "BCC'd: %s.", implode( ', ', $aCCs[ 'bcc' ] ) );
					}
					if ( !empty( $aCCs[ 'cc' ] ) ) {
						$aMsg[] = sprintf( "CC'd: %s.", implode( ', ', $aCCs[ 'cc' ] ) );
					}
				}
			}

			// Where was the wp_mail function called from
			if ( !empty( $aBacktrace ) ) {
				$aMsg[] = sprintf( _wpsf__( 'The "%s" function was called from the file "%s" on line %s.' ),
					'wp_mail',
					$aBacktrace[ 'file' ],
					$aBacktrace[ 'line' ]
				);
			}
		}
		else {
			$aMsg[] = sprintf( _wpsf__( 'Attempting to log email, but data was not of the correct type (%s)' ), 'array' );
		}

		$this->add( 'emails', 'email_attempt_send', 1, implode( " ", $aMsg ) );

		return $aEmail;
	}

	/**
	 * @param array $aHeaders
	 * @return array
	 */
	private function extractCcFromHeaders( $aHeaders ) {
		$aCCs = array(
			'bcc' => [],
			'cc'  => []
		);

		$aHeaders = array_filter( array_map( 'trim', array_map( 'strtolower', $aHeaders ) ) );
		foreach ( $aHeaders as $sHeader ) {
			if ( preg_match( '#^\s*b?cc\s*:.+#i', $sHeader ) ) {
				list( $sHead, $sEmails ) = explode( ':', str_replace( ' ', '', $sHeader ), 2 );
				if ( strpos( ',', $sEmails ) !== false ) {
					$aEmails = explode( ',', $sEmails );
				}
				else {
					$aEmails = [ $sEmails ];
				}

				if ( isset( $aCCs[ $sHead ] ) ) {
					$aCCs[ $sHead ][] = array_unique( array_merge(
						$aCCs[ $sHead ],
						array_filter( $aEmails,
							function ( $sEmail ) {
								return Services::Data()->validEmail( $sEmail );
							} )
					) );
				}
			}
		}
		return $aCCs;
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