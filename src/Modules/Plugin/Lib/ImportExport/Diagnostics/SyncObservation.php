<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Diagnostics;

class SyncObservation {

	public const MAX_ENCODED_BYTES = 1024;

	public const PHASE_NOTIFICATION = 'notification';
	public const PHASE_CLIENT_IMPORT = 'client_import';
	public const PHASE_VERIFICATION = 'verification';
	public const PHASE_EXPORT = 'export';

	public const VERIFICATION_ESTABLISHED = 'established';
	public const VERIFICATION_FAILED = 'failed';
	public const VERIFICATION_NOT_APPLICABLE = 'not_applicable';

	public const RESULT_LOCAL_TARGET_VALIDATION_FAILED = 'local_target_validation_failed';
	public const RESULT_SENDER_EXCEPTION = 'sender_exception';
	public const RESULT_NO_HTTP_RESPONSE = 'no_http_response';
	public const RESULT_HTTP_RESPONSE_RECEIVED = 'http_response_received';

	public const RESULT_LOCAL_INPUT_VALIDATION_FAILED = 'local_input_validation_failed';
	public const RESULT_TRANSPORT_FAILURE = 'transport_failure';
	public const RESULT_EMPTY_RESPONSE = 'empty_response';
	public const RESULT_INVALID_RESPONSE = 'invalid_response';
	public const RESULT_PARSED_REJECTION = 'parsed_rejection';
	public const RESULT_INVALID_EXPORT_DATA = 'invalid_export_data';
	public const RESULT_LOCAL_IMPORT_EXCEPTION = 'local_import_exception';
	public const RESULT_NETWORK_IMPORT_COMPLETED = 'network_import_completed';

	public const RESULT_INVALID_CLAIMED_URL = 'invalid_claimed_url';
	public const RESULT_NO_AUTHORIZED_ROW = 'no_authorized_row';
	public const RESULT_TRUSTED_TARGET_VALIDATION_FAILED = 'trusted_target_validation_failed';
	public const RESULT_MISSING_ID = 'missing_id';
	public const RESULT_MISMATCHED_ID = 'mismatched_id';
	public const RESULT_CALLBACK_COOLDOWN = 'callback_cooldown';
	public const RESULT_CALLBACK_TRANSPORT_FAILURE = 'callback_transport_failure';
	public const RESULT_CALLBACK_INVALID_RESPONSE = 'callback_invalid_response';
	public const RESULT_CALLBACK_DID_NOT_CONFIRM = 'callback_did_not_confirm';
	public const RESULT_VERIFICATION_PASSED = 'verification_passed';

	public const RESULT_EXPORT_COOLDOWN = 'export_cooldown';
	public const RESULT_EXPORT_SERVED = 'export_served';
	public const RESULT_EXPORT_EXCEPTION = 'export_exception';

	public const ERROR_TIMEOUT = 'timeout';
	public const ERROR_REMOTE_COOLDOWN = 'remote_cooldown';
	public const ERROR_REMOTE_EXPORT_EXCEPTION = 'remote_export_exception';

	private const RESULTS_BY_PHASE = [
		self::PHASE_NOTIFICATION => [
			self::RESULT_LOCAL_TARGET_VALIDATION_FAILED,
			self::RESULT_SENDER_EXCEPTION,
			self::RESULT_NO_HTTP_RESPONSE,
			self::RESULT_HTTP_RESPONSE_RECEIVED,
		],
		self::PHASE_CLIENT_IMPORT => [
			self::RESULT_LOCAL_INPUT_VALIDATION_FAILED,
			self::RESULT_TRANSPORT_FAILURE,
			self::RESULT_EMPTY_RESPONSE,
			self::RESULT_INVALID_RESPONSE,
			self::RESULT_PARSED_REJECTION,
			self::RESULT_INVALID_EXPORT_DATA,
			self::RESULT_LOCAL_IMPORT_EXCEPTION,
			self::RESULT_NETWORK_IMPORT_COMPLETED,
		],
		self::PHASE_VERIFICATION => [
			self::RESULT_INVALID_CLAIMED_URL,
			self::RESULT_NO_AUTHORIZED_ROW,
			self::RESULT_TRUSTED_TARGET_VALIDATION_FAILED,
			self::RESULT_MISSING_ID,
			self::RESULT_MISMATCHED_ID,
			self::RESULT_CALLBACK_COOLDOWN,
			self::RESULT_CALLBACK_TRANSPORT_FAILURE,
			self::RESULT_CALLBACK_INVALID_RESPONSE,
			self::RESULT_CALLBACK_DID_NOT_CONFIRM,
			self::RESULT_VERIFICATION_PASSED,
		],
		self::PHASE_EXPORT => [
			self::RESULT_EXPORT_COOLDOWN,
			self::RESULT_EXPORT_SERVED,
			self::RESULT_EXPORT_EXCEPTION,
		],
	];

	private const VERIFICATION_VALUES = [
		self::VERIFICATION_ESTABLISHED,
		self::VERIFICATION_FAILED,
		self::VERIFICATION_NOT_APPLICABLE,
	];

	private const ERROR_CATEGORIES = [
		self::ERROR_TIMEOUT,
		self::ERROR_REMOTE_COOLDOWN,
		self::ERROR_REMOTE_EXPORT_EXCEPTION,
	];

	public static function create(
		int $observedAt,
		string $phase,
		string $result,
		string $verification,
		array $optional = []
	) :?array {
		$observation = [
			'observed_at' => $observedAt,
			'phase'       => $phase,
			'result'      => $result,
			'verification' => $verification,
		];

		foreach ( [ 'http_status', 'error_category', 'eligible_at', 'target_fingerprint' ] as $key ) {
			if ( \array_key_exists( $key, $optional ) ) {
				$observation[ $key ] = $optional[ $key ];
			}
		}

		return self::normalize( $observation );
	}

	public static function normalize( $raw ) :?array {
		if ( !\is_array( $raw ) ) {
			return null;
		}

		$observedAt = $raw[ 'observed_at' ] ?? null;
		$phase = $raw[ 'phase' ] ?? null;
		$result = $raw[ 'result' ] ?? null;
		$verification = $raw[ 'verification' ] ?? null;
		if ( !\is_int( $observedAt ) || $observedAt <= 0
			 || !\is_string( $phase ) || !isset( self::RESULTS_BY_PHASE[ $phase ] )
			 || !\is_string( $result ) || !\in_array( $result, self::RESULTS_BY_PHASE[ $phase ], true )
			 || !\is_string( $verification ) || !\in_array( $verification, self::VERIFICATION_VALUES, true ) ) {
			return null;
		}

		$normalized = [
			'observed_at' => $observedAt,
			'phase'       => $phase,
			'result'      => $result,
			'verification' => $verification,
		];

		if ( \array_key_exists( 'http_status', $raw ) ) {
			if ( !\is_int( $raw[ 'http_status' ] ) || $raw[ 'http_status' ] < 100 || $raw[ 'http_status' ] > 599 ) {
				return null;
			}
			$normalized[ 'http_status' ] = $raw[ 'http_status' ];
		}

		if ( \array_key_exists( 'error_category', $raw ) ) {
			if ( !\is_string( $raw[ 'error_category' ] ) || !\in_array( $raw[ 'error_category' ], self::ERROR_CATEGORIES, true ) ) {
				return null;
			}
			$normalized[ 'error_category' ] = $raw[ 'error_category' ];
		}

		if ( \array_key_exists( 'eligible_at', $raw ) ) {
			if ( !\is_int( $raw[ 'eligible_at' ] ) || $raw[ 'eligible_at' ] <= 0 ) {
				return null;
			}
			$normalized[ 'eligible_at' ] = $raw[ 'eligible_at' ];
		}

		if ( \array_key_exists( 'target_fingerprint', $raw ) ) {
			$fingerprint = $raw[ 'target_fingerprint' ];
			if ( $phase !== self::PHASE_CLIENT_IMPORT
				 || !\is_string( $fingerprint )
				 || \preg_match( '#^[a-f0-9]{64}$#', $fingerprint ) !== 1 ) {
				return null;
			}
			$normalized[ 'target_fingerprint' ] = $fingerprint;
		}

		$encoded = \json_encode( $normalized, \JSON_UNESCAPED_SLASHES );
		return \is_string( $encoded ) && \strlen( $encoded ) <= self::MAX_ENCODED_BYTES ? $normalized : null;
	}

	public static function targetFingerprint( string $canonicalUrl ) :string {
		return \hash( 'sha256', $canonicalUrl );
	}
}
