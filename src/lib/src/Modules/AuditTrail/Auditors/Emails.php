<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;

use FernleafSystems\Wordpress\Services\Services;

class Emails extends Base {

	protected function run() {
		add_filter( 'wp_mail', [ $this, 'auditEmailSend' ], PHP_INT_MAX );
	}

	/**
	 * @param array $email
	 * @return array
	 */
	public function auditEmailSend( $email ) {

		if ( is_array( $email ) ) {

			$to = $email[ 'to' ] ?? __( 'not provided', 'wp-simple-firewall' );
			if ( is_array( $to ) ) {
				$to = implode( ', ', $to );
			}

			$auditData = [
				'to'      => $to,
				'subject' => $email[ 'subject' ],
			];

			// Attempt to capture BCC/CC
			$CCs = [];
			if ( !empty( $email[ 'headers' ] ) ) {
				$headers = $email[ 'headers' ];
				if ( is_string( $headers ) ) {
					$headers = explode( "\n", $headers );
				}
				if ( is_array( $headers ) ) {
					$CCs = $this->extractCcFromHeaders( $headers );
				}
			}
			$auditData[ 'cc' ] = empty( $CCs[ 'cc' ] ) ? '-' : implode( ',', $CCs[ 'cc' ] );
			$auditData[ 'bcc' ] = empty( $CCs[ 'bcc' ] ) ? '-' : implode( ',', $CCs[ 'bcc' ] );

			// Where was the wp_mail function called from
			$backtrace = $this->findEmailSenderBacktrace();
			$auditData[ 'bt_file' ] = empty( $backtrace[ 'file' ] ) ? 'unavailable' : str_replace( ABSPATH, '', $backtrace[ 'file' ] );
			$auditData[ 'bt_line' ] = empty( $backtrace[ 'line' ] ) ? 'unavailable' : $backtrace[ 'line' ];

			$this->getCon()->fireEvent( 'email_attempt_send', [ 'audit_params' => $auditData ] );
		}

		return $email;
	}

	private function extractCcFromHeaders( array $headers ) :array {
		$CCs = [
			'bcc' => [],
			'cc'  => []
		];

		$headers = array_filter( array_map( 'trim', array_map( 'strtolower', $headers ) ) );
		foreach ( $headers as $header ) {
			if ( preg_match( '#^\s*b?cc\s*:.+#i', $header ) ) {
				list( $head, $emails ) = explode( ':', str_replace( ' ', '', $header ), 2 );
				if ( strpos( ',', $emails ) !== false ) {
					$emails = explode( ',', $emails );
				}
				else {
					$emails = [ $emails ];
				}

				if ( isset( $CCs[ $head ] ) ) {
					$CCs[ $head ][] = array_unique( array_merge(
						$CCs[ $head ],
						array_filter( $emails,
							function ( $sEmail ) {
								return Services::Data()->validEmail( $sEmail );
							} )
					) );
				}
			}
		}
		return $CCs;
	}

	private function findEmailSenderBacktrace() :array {
		$backtrace = [];
		foreach ( debug_backtrace( false ) as $item ) {
			if ( isset( $item[ 'function' ] ) && 'wp_mail' === strtolower( $item[ 'function' ] ) ) {
				$backtrace = $item;
				break;
			}
		}
		return $backtrace;
	}
}