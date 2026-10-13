<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\Sites;

class InvitationMetadata {

	public const META_KEY = 'invitation';
	public const RESULT_STARTED = 'started';
	public const RESULT_HTTP_RESPONSE = 'http_response';
	public const RESULT_TRANSPORT_FAILURE = 'transport_failure';
	public const RESULT_HTTP_FAILURE = 'http_failure';
	public const RESULT_URL_VALIDATION_FAILURE = 'url_validation_failure';
	public const RESULT_SENDER_FAILURE = 'sender_failure';
	public const MAX_ATTEMPTS = 3;

	public function newCycle() :array {
		return [
			'cycle_id'               => \wp_generate_uuid4(),
			'attempts_started'        => 0,
			'last_attempt_started_at' => null,
			'last_result'             => null,
			'last_http_status'        => null,
		];
	}

	public function normalize( array $meta ) :array {
		$invitation = \is_array( $meta[ self::META_KEY ] ?? null ) ? $meta[ self::META_KEY ] : [];
		$result = (string)( $invitation[ 'last_result' ] ?? '' );
		if ( !\in_array( $result, self::resultKeys(), true ) ) {
			$result = '';
		}

		$status = (int)( $invitation[ 'last_http_status' ] ?? 0 );
		return [
			'cycle_id'               => (string)( $invitation[ 'cycle_id' ] ?? '' ),
			'attempts_started'        => \min( self::MAX_ATTEMPTS, \max( 0, (int)( $invitation[ 'attempts_started' ] ?? 0 ) ) ),
			'last_attempt_started_at' => (int)( $invitation[ 'last_attempt_started_at' ] ?? 0 ) ?: null,
			'last_result'             => $result === '' ? null : $result,
			'last_http_status'        => $status > 0 ? $status : null,
		];
	}

	public function replace( array $meta, array $invitation ) :array {
		$meta[ self::META_KEY ] = $this->normalize( [ self::META_KEY => $invitation ] );
		return $meta;
	}

	public function remove( array $meta ) :array {
		unset( $meta[ self::META_KEY ] );
		return $meta;
	}

	public function startAttempt( array $meta, int $startedAt ) :array {
		$invitation = $this->normalize( $meta );
		if ( $invitation[ 'cycle_id' ] === '' ) {
			$invitation = $this->newCycle();
		}
		$invitation[ 'attempts_started' ]++;
		$invitation[ 'last_attempt_started_at' ] = $startedAt;
		$invitation[ 'last_result' ] = self::RESULT_STARTED;
		$invitation[ 'last_http_status' ] = null;
		return $this->replace( $meta, $invitation );
	}

	public function withResult( array $meta, string $result, int $httpStatus = 0 ) :array {
		if ( !\in_array( $result, self::resultKeys(), true ) || $result === self::RESULT_STARTED ) {
			$result = self::RESULT_SENDER_FAILURE;
		}
		$invitation = $this->normalize( $meta );
		$invitation[ 'last_result' ] = $result;
		$invitation[ 'last_http_status' ] = $httpStatus > 0 ? $httpStatus : null;
		return $this->replace( $meta, $invitation );
	}

	public static function isFailure( string $result ) :bool {
		return \in_array( $result, [
			self::RESULT_TRANSPORT_FAILURE,
			self::RESULT_HTTP_FAILURE,
			self::RESULT_URL_VALIDATION_FAILURE,
			self::RESULT_SENDER_FAILURE,
		], true );
	}

	private static function resultKeys() :array {
		return [
			self::RESULT_STARTED,
			self::RESULT_HTTP_RESPONSE,
			self::RESULT_TRANSPORT_FAILURE,
			self::RESULT_HTTP_FAILURE,
			self::RESULT_URL_VALIDATION_FAILURE,
			self::RESULT_SENDER_FAILURE,
		];
	}
}
