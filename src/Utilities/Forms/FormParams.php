<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Forms;

class FormParams {

	public const ENC_BASE64 = 'b64';
	public const ENC_JSON = 'json';
	public const ENC_OBSCURE = 'obscure';

	private const OBSCURE_PREFIX = 'icwp-';

	/**
	 * Decodes the form carried in a request's data: an encoded 'form_params' field when present, otherwise the
	 * data itself. The data comes from the request, so nothing about its shape is assumed: anything that isn't a
	 * form this plugin sends decodes to an empty form.
	 */
	public static function Decode( array $data ) :array {
		$raw = $data[ 'form_params' ] ?? '';

		if ( empty( $raw ) ) {
			$form = $data;
		}
		elseif ( \is_array( $raw ) ) {
			$form = $raw;
		}
		elseif ( \is_string( $raw ) ) {
			$encodings = self::Encodings( $data[ 'form_enc' ] ?? null );
			$form = $encodings === null ? [] : self::DecodeString( $raw, $encodings );
		}
		else {
			$form = [];
		}

		return $form;
	}

	/**
	 * @param mixed $formEnc
	 * @return string[]|null the encodings to reverse, or null when they aren't a list of encoding names
	 */
	private static function Encodings( $formEnc ) :?array {
		if ( empty( $formEnc ) ) {
			$encodings = [ self::ENC_BASE64 ];
		}
		elseif ( \is_array( $formEnc ) && \count( \array_filter( $formEnc, '\is_string' ) ) === \count( $formEnc ) ) {
			$encodings = $formEnc;
		}
		else {
			$encodings = null;
		}
		return $encodings;
	}

	/**
	 * Reverses the encodings in the opposite order to how they're applied: the form is JSON encoded, then base64
	 * encoded, then prefixed when obscured.
	 */
	private static function DecodeString( string $raw, array $encodings ) :array {
		if ( \in_array( self::ENC_OBSCURE, $encodings, true ) && \str_starts_with( $raw, self::OBSCURE_PREFIX ) ) {
			$raw = \substr( $raw, \strlen( self::OBSCURE_PREFIX ) );
		}
		if ( \in_array( self::ENC_BASE64, $encodings, true ) ) {
			$raw = \base64_decode( $raw, true );
		}

		if ( !\is_string( $raw ) ) {
			$form = [];
		}
		elseif ( \in_array( self::ENC_JSON, $encodings, true ) ) {
			$decoded = \json_decode( $raw, true );
			$form = \is_array( $decoded ) ? $decoded : [];
		}
		else {
			\parse_str( $raw, $form );
		}

		return $form;
	}
}
