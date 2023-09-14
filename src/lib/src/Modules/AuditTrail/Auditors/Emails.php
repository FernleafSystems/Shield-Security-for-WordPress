<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;

use FernleafSystems\Wordpress\Services\Services;

class Emails extends Base {

	protected function initAuditHooks() :void {
		add_filter( 'wp_mail', [ $this, 'auditEmailSend' ], \PHP_INT_MAX );
	}

	/**
	 * @param array $email
	 * @return array
	 */
	public function auditEmailSend( $email ) {

		if ( \is_array( $email ) ) {

			$to = $email[ 'to' ] ?? __( 'not provided', 'wp-simple-firewall' );
			if ( \is_array( $to ) ) {
				$to = \implode( ', ', $to );
			}

			$auditData = [
				'to'      => $to,
				'subject' => $email[ 'subject' ],
			];

			// Attempt to capture BCC/CC
			$CCs = [];
			if ( !empty( $email[ 'headers' ] ) ) {
				$headers = $email[ 'headers' ];
				if ( \is_string( $headers ) ) {
					$headers = \explode( "\n", $headers );
				}
				if ( \is_array( $headers ) ) {
					$CCs = $this->extractCcFromHeaders( $headers );
				}
			}
			$auditData[ 'cc' ] = empty( $CCs[ 'cc' ] ) ? '-' : \implode( ', ', $CCs[ 'cc' ] );
			$auditData[ 'bcc' ] = empty( $CCs[ 'bcc' ] ) ? '-' : \implode( ', ', $CCs[ 'bcc' ] );

			// Where was the wp_mail function called from
			$backtrace = $this->findEmailSenderBacktrace();
			$auditData[ 'bt_file' ] = empty( $backtrace[ 'file' ] ) ? 'unavailable' : \str_replace( \ABSPATH, '', $backtrace[ 'file' ] );
			$auditData[ 'bt_line' ] = empty( $backtrace[ 'line' ] ) ? 'unavailable' : $backtrace[ 'line' ];

			self::con()->fireEvent( 'email_attempt_send', [ 'audit_params' => $auditData ] );
		}

		return $email;
	}

	private function extractCcFromHeaders( array $headers ) :array {
		$CCs = [
			'bcc' => [],
			'cc'  => []
		];

		$headers = \array_filter(
			\array_map( function ( $header ) {
				return \str_replace( ' ', '', \trim( \strtolower( $header ) ) );
			}, $headers ),
			function ( $header ) {
				return !empty( $header ) && \preg_match( '#^\s*b?cc\s*:.+$#i', $header );
			}
		);

		foreach ( $headers as $header ) {
			[ $headerKey, $emails ] = \explode( ':', $header, 2 );

			$emails = \array_filter(
				\array_map( '\trim', \explode( ',', $emails ) ),
				function ( $email ) {
					return Services::Data()->validEmail( $email );
				}
			);

			if ( isset( $CCs[ $headerKey ] ) ) {
				$CCs[ $headerKey ] = \array_unique( \array_merge( $CCs[ $headerKey ], $emails ) );
			}
		}
		return $CCs;
	}

	private function findEmailSenderBacktrace() :array {
		$backtrace = [];
		foreach ( \debug_backtrace( false ) as $item ) {
			if ( isset( $item[ 'function' ] ) && 'wp_mail' === \strtolower( $item[ 'function' ] ) ) {
				$backtrace = $item;
				break;
			}
		}
		return $backtrace;
	}
}