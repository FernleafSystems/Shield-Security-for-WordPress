<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Text;

class SafeDisplayText {

	public const DEFAULT_MAX_BYTES = 2048;
	public const TRUNCATION_SUFFIX = ' (...truncated)';

	/**
	 * @param mixed $value
	 */
	public static function inline( $value, int $maxBytes = self::DEFAULT_MAX_BYTES ) :string {
		$text = self::normaliseUtf8( self::stringify( $value ) );
		$text = \str_replace( [ "\r\n", "\r", "\n" ], ' ', $text );
		$text = \preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', $text ) ?? $text;
		$text = \preg_replace( '/[ \t]+/u', ' ', $text ) ?? $text;

		return self::truncate( \trim( $text ), $maxBytes );
	}

	public static function truncate( string $text, int $maxBytes = self::DEFAULT_MAX_BYTES ) :string {
		$text = self::normaliseUtf8( $text );
		if ( $maxBytes < 1 || \strlen( $text ) <= $maxBytes ) {
			return $text;
		}

		return \rtrim( self::normaliseUtf8( (string)\substr( $text, 0, $maxBytes ) ) ).self::TRUNCATION_SUFFIX;
	}

	/**
	 * @param mixed $value
	 */
	private static function stringify( $value ) :string {
		if ( $value === null ) {
			return '';
		}
		if ( \is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}
		if ( \is_scalar( $value ) ) {
			return (string)$value;
		}

		$json = \json_encode( $value, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PARTIAL_OUTPUT_ON_ERROR );
		if ( \is_string( $json ) ) {
			return $json;
		}

		return \is_object( $value ) ? '[object]' : '[array]';
	}

	private static function normaliseUtf8( string $text ) :string {
		if ( $text === '' ) {
			return '';
		}

		if ( \function_exists( 'wp_check_invalid_utf8' ) ) {
			return wp_check_invalid_utf8( $text, true );
		}

		if ( \preg_match( '//u', $text ) === 1 ) {
			return $text;
		}

		if ( \function_exists( 'iconv' ) ) {
			$converted = @\iconv( 'UTF-8', 'UTF-8//IGNORE', $text );
			if ( \is_string( $converted ) ) {
				return $converted;
			}
		}

		return \preg_replace( '/[^\x09\x0A\x0D\x20-\x7E]/', '', $text ) ?? '';
	}
}
