<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\Diagnostics;

class ObservationPresenter {

	public function present( ?array $observation ) :array {
		$observation = SyncObservation::normalize( $observation );
		if ( $observation === null ) {
			return [
				'has_result' => false,
				'label'      => __( 'No diagnostic result recorded', 'wp-simple-firewall' ),
			];
		}

		$result = $observation[ 'result' ];
		$copy = $this->copyForResult( $result, $observation[ 'error_category' ] ?? null );
		$qualification = $observation[ 'phase' ] === SyncObservation::PHASE_VERIFICATION
			&& $observation[ 'verification' ] === SyncObservation::VERIFICATION_FAILED
			? __( 'Unverified: a request claiming this site\'s URL.', 'wp-simple-firewall' )
			: null;
		return [
			'has_result'          => true,
			'phase'               => $observation[ 'phase' ],
			'result'              => $result,
			'label'               => $copy[ 'label' ],
			'explanation'         => $copy[ 'explanation' ],
			'next_check'          => $copy[ 'next_check' ],
			'observed_at'         => $observation[ 'observed_at' ],
			'observed_at_display' => \wp_date( 'Y-m-d H:i:s T', $observation[ 'observed_at' ] ),
			'http_status'         => $observation[ 'http_status' ] ?? null,
			'verification'        => $observation[ 'verification' ],
			'qualification'       => $qualification,
			'eligible_at'         => $observation[ 'eligible_at' ] ?? null,
			'eligible_at_display' => isset( $observation[ 'eligible_at' ] )
				? \wp_date( 'Y-m-d H:i:s T', $observation[ 'eligible_at' ] )
				: null,
		];
	}

	public function failureMessage( ?array $observation, string $fallback ) :string {
		$presented = $this->present( $observation );
		return $presented[ 'has_result' ]
			? \trim( (string)$presented[ 'explanation' ].' '.(string)$presented[ 'next_check' ] )
			: $fallback;
	}

	private function copyForResult( string $result, ?string $errorCategory ) :array {
		$transport = __( 'The request did not receive an HTTP response.', 'wp-simple-firewall' );
		$reachability = __( 'Check reachability and hosting or firewall restrictions from this site.', 'wp-simple-firewall' );
		$emptyResponse = __( 'The endpoint returned an empty response.', 'wp-simple-firewall' );
		$invalidResponse = __( 'The endpoint returned an invalid or unusable response.', 'wp-simple-firewall' );
		$inspectResponse = __( 'Check the endpoint for redirects, caching, or an intervening HTML or error response.', 'wp-simple-firewall' );
		$callbackNextCheck = __( 'Check the master\'s ability to reach the client and the client\'s existing handshake eligibility.', 'wp-simple-firewall' );
		$serverLogs = __( 'Inspect the affected site\'s existing server and plugin logs.', 'wp-simple-firewall' );
		if ( $errorCategory === SyncObservation::ERROR_REMOTE_COOLDOWN ) {
			$parsedRejection = [
				__( 'Import request rejected', 'wp-simple-firewall' ),
				__( 'The master asked this site to wait before trying again.', 'wp-simple-firewall' ),
				__( 'Wait before another manual attempt.', 'wp-simple-firewall' ),
			];
		}
		elseif ( $errorCategory === SyncObservation::ERROR_REMOTE_EXPORT_EXCEPTION ) {
			$parsedRejection = [
				__( 'Master export exception', 'wp-simple-firewall' ),
				__( 'The master could not finish serving the settings export.', 'wp-simple-firewall' ),
				__( 'Inspect the affected master\'s existing server and plugin logs.', 'wp-simple-firewall' ),
			];
		}
		else {
			$parsedRejection = [
				__( 'Import request rejected', 'wp-simple-firewall' ),
				__( 'The master rejected the network import request.', 'wp-simple-firewall' ),
				__( 'Compare the configured master URL, Import ID, and authorized site entry.', 'wp-simple-firewall' ),
			];
		}

		$map = [
			SyncObservation::RESULT_LOCAL_TARGET_VALIDATION_FAILED => [ __( 'Target validation failed', 'wp-simple-firewall' ), __( 'The configured target did not pass local validation.', 'wp-simple-firewall' ), __( 'Compare the configured URL with the authorized site entry.', 'wp-simple-firewall' ) ],
			SyncObservation::RESULT_SENDER_EXCEPTION => [ __( 'Notification sender exception', 'wp-simple-firewall' ), __( 'The notification sender stopped with a local exception.', 'wp-simple-firewall' ), $serverLogs ],
			SyncObservation::RESULT_NO_HTTP_RESPONSE => [ __( 'No notification response', 'wp-simple-firewall' ), $transport, $reachability ],
			SyncObservation::RESULT_HTTP_RESPONSE_RECEIVED => [ __( 'Notification response received', 'wp-simple-firewall' ), __( 'The notification request received an HTTP response. Client acceptance and import are unconfirmed.', 'wp-simple-firewall' ), __( 'Inspect the client site\'s local import result if synchronization still appears wrong.', 'wp-simple-firewall' ) ],
			SyncObservation::RESULT_LOCAL_INPUT_VALIDATION_FAILED => [ __( 'Local input validation failed', 'wp-simple-firewall' ), __( 'The network import input did not pass local validation.', 'wp-simple-firewall' ), __( 'Check the configured master URL and connection details.', 'wp-simple-firewall' ) ],
			SyncObservation::RESULT_TRANSPORT_FAILURE => [ __( 'Transport failure', 'wp-simple-firewall' ), $transport, $reachability ],
			SyncObservation::RESULT_EMPTY_RESPONSE => [ __( 'Empty response', 'wp-simple-firewall' ), $emptyResponse, $inspectResponse ],
			SyncObservation::RESULT_INVALID_RESPONSE => [ __( 'Invalid response', 'wp-simple-firewall' ), $invalidResponse, $inspectResponse ],
			SyncObservation::RESULT_PARSED_REJECTION => $parsedRejection,
			SyncObservation::RESULT_INVALID_EXPORT_DATA => [ __( 'Invalid export data', 'wp-simple-firewall' ), __( 'The response was accepted but its export data was not usable.', 'wp-simple-firewall' ), $inspectResponse ],
			SyncObservation::RESULT_LOCAL_IMPORT_EXCEPTION => [ __( 'Local import exception', 'wp-simple-firewall' ), __( 'This site could not finish applying the network import.', 'wp-simple-firewall' ), $serverLogs ],
			SyncObservation::RESULT_NETWORK_IMPORT_COMPLETED => [ __( 'Network import completed', 'wp-simple-firewall' ), __( 'Network import completed on this site.', 'wp-simple-firewall' ), __( 'Review this site\'s settings if synchronization still appears wrong.', 'wp-simple-firewall' ) ],
			SyncObservation::RESULT_INVALID_CLAIMED_URL => [ __( 'Invalid claimed URL', 'wp-simple-firewall' ), __( 'An unverified request could not be associated with an authorized site.', 'wp-simple-firewall' ), __( 'Compare the configured URL with the authorized site entry.', 'wp-simple-firewall' ) ],
			SyncObservation::RESULT_NO_AUTHORIZED_ROW => [ __( 'No authorized site', 'wp-simple-firewall' ), __( 'An unverified request could not be associated with an authorized site.', 'wp-simple-firewall' ), __( 'Compare the configured URL with the authorized site entry.', 'wp-simple-firewall' ) ],
			SyncObservation::RESULT_TRUSTED_TARGET_VALIDATION_FAILED => [ __( 'Trusted target validation failed', 'wp-simple-firewall' ), __( 'A request claiming this site\'s URL failed trusted-target validation.', 'wp-simple-firewall' ), __( 'Compare the configured URL with the authorized site entry.', 'wp-simple-firewall' ) ],
			SyncObservation::RESULT_MISSING_ID => [ __( 'Import ID missing', 'wp-simple-firewall' ), __( 'A request claiming this site\'s URL did not supply its required Import ID.', 'wp-simple-firewall' ), __( 'Compare the client Import ID and authorized master entry.', 'wp-simple-firewall' ) ],
			SyncObservation::RESULT_MISMATCHED_ID => [ __( 'Import ID mismatch', 'wp-simple-firewall' ), __( 'A request claiming this site\'s URL supplied a different Import ID.', 'wp-simple-firewall' ), __( 'Compare the client Import ID and authorized master entry.', 'wp-simple-firewall' ) ],
			SyncObservation::RESULT_CALLBACK_COOLDOWN => [ __( 'Callback cooldown active', 'wp-simple-firewall' ), __( 'The verification callback is still within its cooldown.', 'wp-simple-firewall' ), __( 'Wait until the displayed eligibility time before another manual attempt.', 'wp-simple-firewall' ) ],
			SyncObservation::RESULT_CALLBACK_TRANSPORT_FAILURE => [ __( 'Callback transport failure', 'wp-simple-firewall' ), $transport, $callbackNextCheck ],
			SyncObservation::RESULT_CALLBACK_INVALID_RESPONSE => [ __( 'Invalid callback response', 'wp-simple-firewall' ), $invalidResponse, $callbackNextCheck ],
			SyncObservation::RESULT_CALLBACK_DID_NOT_CONFIRM => [ __( 'Callback did not confirm', 'wp-simple-firewall' ), __( 'The callback response did not confirm the existing handshake.', 'wp-simple-firewall' ), $callbackNextCheck ],
			SyncObservation::RESULT_VERIFICATION_PASSED => [ __( 'Verification passed', 'wp-simple-firewall' ), __( 'The existing export verification completed successfully.', 'wp-simple-firewall' ), __( 'Inspect the client\'s local import result if synchronization still appears wrong.', 'wp-simple-firewall' ) ],
			SyncObservation::RESULT_EXPORT_COOLDOWN => [ __( 'Export cooldown active', 'wp-simple-firewall' ), __( 'The settings export is still within its cooldown.', 'wp-simple-firewall' ), __( 'Wait until the displayed eligibility time before another manual attempt.', 'wp-simple-firewall' ) ],
			SyncObservation::RESULT_EXPORT_SERVED => [ __( 'Export served', 'wp-simple-firewall' ), __( 'The master served a settings export. Client application is unconfirmed.', 'wp-simple-firewall' ), __( 'Inspect the client\'s local import result if synchronization still appears wrong.', 'wp-simple-firewall' ) ],
			SyncObservation::RESULT_EXPORT_EXCEPTION => [ __( 'Export exception', 'wp-simple-firewall' ), __( 'The master could not finish serving the settings export.', 'wp-simple-firewall' ), $serverLogs ],
		];

		$copy = $map[ $result ];
		return [ 'label' => $copy[ 0 ], 'explanation' => $copy[ 1 ], 'next_check' => $copy[ 2 ] ];
	}
}
