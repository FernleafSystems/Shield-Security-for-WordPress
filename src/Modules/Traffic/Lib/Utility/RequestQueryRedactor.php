<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\Utility;

class RequestQueryRedactor {

	public const FILTER_SENSITIVE_QUERY_KEYS = 'shield/request_logs/sensitive_query_keys';
	public const FILTER_REDACTED_QUERY = 'shield/request_logs/redacted_query';

	private const REDACTED_VALUE = 'redacted';

	private const DEFAULT_SENSITIVE_KEYS = [
		'key',
		'token',
		'access_token',
		'refresh_token',
		'id_token',
		'oauth_token',
		'oauth_verifier',
		'secret',
		'client_secret',
		'consumer_secret',
		'password',
		'pass',
		'pwd',
		'auth',
		'authorization',
		'signature',
		'sig',
		'api_key',
		'apikey',
		'license',
		'license_key',
		'code',
		'nonce',
		'_wpnonce',
		'wpnonce',
		'exnonce',
	];

	public function redact( string $query ) :string {
		$query = \ltrim( \trim( $query ), '?' );
		if ( $query === '' ) {
			return '';
		}

		$sensitiveKeys = $this->sensitiveKeys( $query );
		$parts = \preg_split( '#([&;])#', $query, -1, \PREG_SPLIT_DELIM_CAPTURE );
		$parts = \is_array( $parts ) ? $parts : [ $query ];
		$redacted = \implode( '', \array_map(
			fn( string $part ) :string => \in_array( $part, [ '&', ';' ], true )
				? $part
				: $this->redactQueryPart( $part, $sensitiveKeys ),
			$parts
		) );

		return (string)\apply_filters( self::FILTER_REDACTED_QUERY, $redacted, $query, $sensitiveKeys );
	}

	private function redactQueryPart( string $part, array $sensitiveKeys ) :string {
		if ( $part === '' ) {
			return $part;
		}

		$separatorPos = \strpos( $part, '=' );
		$key = $separatorPos === false ? $part : \substr( $part, 0, $separatorPos );
		if ( !$this->isSensitiveKey( $key, $sensitiveKeys ) ) {
			return $part;
		}

		return $key.'='.self::REDACTED_VALUE;
	}

	private function isSensitiveKey( string $key, array $sensitiveKeys ) :bool {
		foreach ( $this->keySegments( $key ) as $segment ) {
			if ( \in_array( $segment, $sensitiveKeys, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return list<string>
	 */
	private function keySegments( string $key ) :array {
		$decodedKey = $this->normaliseKey( $key );
		$segments = \preg_split( '#[\[\]]+#', $decodedKey, -1, \PREG_SPLIT_NO_EMPTY );
		$segments = \is_array( $segments ) ? $segments : [];
		$segments[] = $decodedKey;

		return \array_values( \array_unique( \array_filter(
			\array_map( fn( string $segment ) :string => $this->normaliseKey( $segment ), $segments ),
			static fn( string $segment ) :bool => $segment !== ''
		) ) );
	}

	/**
	 * @return list<string>
	 */
	private function sensitiveKeys( string $query ) :array {
		$keys = \apply_filters( self::FILTER_SENSITIVE_QUERY_KEYS, self::DEFAULT_SENSITIVE_KEYS, $query );
		if ( !\is_array( $keys ) ) {
			$keys = self::DEFAULT_SENSITIVE_KEYS;
		}

		return \array_values( \array_unique( \array_filter(
			\array_map( fn( $key ) :string => $this->normaliseKey( (string)$key ), $keys ),
			static fn( string $key ) :bool => $key !== ''
		) ) );
	}

	private function normaliseKey( string $key ) :string {
		return \strtolower( \trim( \urldecode( $key ) ) );
	}
}
