<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Delivery;

/**
 * Canonical padded base64 only, as the orchestrator writes it. Re-encoding the decoded bytes must give the input
 * back, which refuses missing padding, stray characters and non-zero trailing bits; a validating pattern would
 * exhaust PCRE's JIT stack on a large file.
 */
final class Base64 {

	public static function decode( string $text ) :?string {
		$bytes = \base64_decode( $text, true );
		return $bytes === false || \base64_encode( $bytes ) !== $text ? null : $bytes;
	}
}
