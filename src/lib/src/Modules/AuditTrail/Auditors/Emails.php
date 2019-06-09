<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;

use FernleafSystems\Wordpress\Services\Services;

class Emails extends Base {

	public function run() {
		add_filter( 'wp_mail', [ $this, 'auditEmailSend' ], PHP_INT_MAX );
	}

	/**
	 * @param array $aEmail
	 * @return array
	 */
	public function auditEmailSend( $aEmail ) {

		if ( is_array( $aEmail ) ) {

			$sTo = isset( $aEmail[ 'to' ] ) ? $aEmail[ 'to' ] : __( 'not provided', 'wp-simple-firewall' );
			if ( is_array( $sTo ) ) {
				$sTo = implode( ', ', $sTo );
			}

			$aData = [
				'to'      => $sTo,
				'subject' => $aEmail[ 'subject' ],
			];

			// Attempt to capture BCC/CC
			$aCCs = [];
			if ( !empty( $aEmail[ 'headers' ] ) ) {
				$aHeaders = $aEmail[ 'headers' ];
				if ( is_string( $aHeaders ) ) {
					$aHeaders = explode( "\n", $aHeaders );
				}
				if ( is_array( $aHeaders ) ) {
					$aCCs = $this->extractCcFromHeaders( $aHeaders );
				}
			}
			$aData[ 'cc' ] = empty( $aCCs[ 'cc' ] ) ? '-' : implode( ',', $aCCs[ 'cc' ] );
			$aData[ 'bcc' ] = empty( $aCCs[ 'bcc' ] ) ? '-' : implode( ',', $aCCs[ 'bcc' ] );

			// Where was the wp_mail function called from
			$aBacktrace = $this->findEmailSenderBacktrace();
			$aData[ 'bt_file' ] = empty( $aBacktrace[ 'file' ] ) ? 'unavailable' : str_replace( ABSPATH, '', $aBacktrace[ 'file' ] );
			$aData[ 'bt_line' ] = empty( $aBacktrace[ 'line' ] ) ? 'unavailable' : $aBacktrace[ 'line' ];

			$this->getCon()->fireEvent( 'email_attempt_send', [ 'audit' => $aData ] );
		}
		else {
			$this->getCon()->fireEvent(
				'email_send_invalid',
				[ 'audit' => [ 'type' => 'array' ] ]
			);
		}

		return $aEmail;
	}

	/**
	 * @param array $aHeaders
	 * @return array
	 */
	private function extractCcFromHeaders( $aHeaders ) {
		$aCCs = [
			'bcc' => [],
			'cc'  => []
		];

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
		$aBT = [];
		foreach ( debug_backtrace( false ) as $aItem ) {
			if ( isset( $aItem[ 'function' ] ) && 'wp_mail' === strtolower( $aItem[ 'function' ] ) ) {
				$aBT = $aItem;
				break;
			}
		}
		return $aBT;
	}
}