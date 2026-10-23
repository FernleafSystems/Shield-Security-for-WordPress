<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Delivery;

/**
 * The file checks for a delivered rule file, in the orchestrator's order (its ADR 0007, "Reader checks"). The
 * envelope signs `orle-vp-signed/1\n<context>\n` followed by the exact payload bytes, so nothing is canonicalised.
 * JSON objects stay objects, so a list where an object belongs, or the reverse, is refused as the orchestrator
 * refuses it. Rule contents are not checked here; each rule is decided when the ruleset is built.
 */
class RuleFileVerifier {

	public const FORMAT = 'orle-vp-signed/1';

	public const CONTEXT = 'asset';

	public const MAX_BYTES = 524288;

	public const SLUG_PATTERN = '/^[a-z0-9][a-z0-9_.-]{0,199}$/D';

	private const ISSUED_AT_LEEWAY_MS = 86400000;

	private const MAX_SAFE_INTEGER = 9007199254740991;

	private const ISSUED_AT_PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/D';

	private TrustedKeys $keys;

	public function __construct( ?TrustedKeys $keys = null ) {
		$this->keys = $keys ?? new TrustedKeys();
	}

	/**
	 * @param int $nowMs the site's clock, in milliseconds
	 * @throws RuleFileRefused
	 */
	public function verify( string $bytes, string $kind, string $slug, int $schema, int $nowMs ) :VerifiedRuleFile {
		if ( \strlen( $bytes ) > self::MAX_BYTES ) {
			throw new RuleFileRefused( 'file_too_large' );
		}
		$envelope = self::object( $bytes );
		if ( $envelope === null || ( $envelope->format ?? null ) !== self::FORMAT || !\is_string( $envelope->keyId ?? null )
			 || !\in_array( $envelope->context ?? null, [ 'asset', 'index' ], true )
			 || !\is_string( $envelope->payload ?? null ) || !\is_string( $envelope->signature ?? null ) ) {
			throw new RuleFileRefused( 'invalid_envelope' );
		}
		$key = $this->keys->find( $envelope->keyId );
		if ( $key === null ) {
			throw new RuleFileRefused( 'untrusted_key' );
		}
		$payloadBytes = Base64::decode( $envelope->payload );
		$signature = Base64::decode( $envelope->signature );
		if ( $payloadBytes === null || $signature === null || \strlen( $signature ) !== 64
			 || !self::signatureVerifies( $signature, self::FORMAT."\n".$envelope->context."\n".$payloadBytes, $key[ 'key' ] ) ) {
			throw new RuleFileRefused( 'invalid_signature' );
		}
		if ( $envelope->context !== self::CONTEXT ) {
			throw new RuleFileRefused( 'wrong_context' );
		}
		$payload = self::object( $payloadBytes );
		$issuedAtMs = $payload === null ? null : self::issuedAtMs( $payload->issuedAt ?? null );
		if ( $payload === null || $issuedAtMs === null || !self::shaped( $payload ) ) {
			throw new RuleFileRefused( 'invalid_payload' );
		}
		if ( $issuedAtMs > $nowMs + self::ISSUED_AT_LEEWAY_MS ) {
			throw new RuleFileRefused( 'future_issued_at' );
		}
		if ( $payload->asset->kind !== $kind || $payload->asset->slug !== $slug ) {
			throw new RuleFileRefused( 'asset_mismatch' );
		}
		if ( $payload->schema !== $schema ) {
			throw new RuleFileRefused( 'schema_mismatch' );
		}
		return new VerifiedRuleFile( $bytes, $envelope->keyId, $key[ 'epoch' ], $payload->sequence, $payload );
	}

	/**
	 * The key a stored file was signed with, read without verifying it, so a file signed by a key no longer trusted
	 * can be discarded without a signature check.
	 */
	public static function keyIdOf( string $bytes ) :?string {
		$envelope = self::object( $bytes );
		return \is_string( $envelope->keyId ?? null ) ? $envelope->keyId : null;
	}

	/**
	 * WordPress bundles sodium_compat for PHP builds without the sodium extension.
	 */
	private static function signatureVerifies( string $signature, string $message, string $publicKey ) :bool {
		if ( !\function_exists( 'sodium_crypto_sign_verify_detached' ) && \defined( 'ABSPATH' ) && \defined( 'WPINC' ) ) {
			$compat = ABSPATH.WPINC.'/sodium_compat/autoload.php';
			if ( \is_file( $compat ) ) {
				require_once $compat;
			}
		}
		if ( !\function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
			return false;
		}
		try {
			return \sodium_crypto_sign_verify_detached( $signature, $message, $publicKey );
		}
		catch ( \SodiumException $e ) {
			return false;
		}
	}

	private static function object( string $json ) :?\stdClass {
		$value = \json_decode( $json, false, 512, \JSON_BIGINT_AS_STRING );
		return $value instanceof \stdClass ? $value : null;
	}

	private static function shaped( \stdClass $payload ) :bool {
		return self::safeInteger( $payload->sequence ?? null ) && $payload->sequence >= 1
			   && self::safeInteger( $payload->schema ?? null ) && $payload->schema >= 1
			   && \is_array( $payload->rules ?? null )
			   && ( $payload->asset ?? null ) instanceof \stdClass
			   && \is_string( $payload->asset->kind ?? null ) && \is_string( $payload->asset->slug ?? null );
	}

	private static function safeInteger( $value ) :bool {
		return \is_int( $value ) && $value >= -self::MAX_SAFE_INTEGER && $value <= self::MAX_SAFE_INTEGER;
	}

	private static function issuedAtMs( $value ) :?int {
		if ( !\is_string( $value ) || !\preg_match( self::ISSUED_AT_PATTERN, $value ) ) {
			return null;
		}
		try {
			return (int)( new \DateTimeImmutable( $value ) )->format( 'Uv' );
		}
		catch ( \Exception $e ) {
			return null;
		}
	}
}
