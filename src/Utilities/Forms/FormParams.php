<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Forms;

class FormParams {

	public const ENC_BASE64 = 'b64';
	public const ENC_JSON = 'json';
	public const ENC_OBSCURE = 'obscure';

	/**
	 * Decodes the form carried in a request's data: an encoded 'form_params' field when present, otherwise the
	 * data itself.
	 */
	public static function Decode( array $data ) :array {
		$formParams = [];
		$raw = $data[ 'form_params' ] ?? '';

		if ( empty( $raw ) ) {
			$formParams = $data;
		}
		elseif ( \is_array( $raw ) ) {
			$formParams = $raw;
		}
		else {
			$formEnc = $data[ 'form_enc' ] ?? null;
			if ( empty( $formEnc ) ) {
				$formEnc = [ self::ENC_BASE64 ];
			}

			if ( \in_array( self::ENC_BASE64, $formEnc ) ) {
				$raw = \base64_decode( $raw );
			}
			if ( \in_array( self::ENC_OBSCURE, $formEnc ) ) {
				$raw = \str_replace( 'icwp-', '', $raw );
			}
			if ( \in_array( self::ENC_JSON, $formEnc ) ) {
				$raw = \json_decode( $raw, true );
			}

			if ( \is_array( $raw ) ) {
				$formParams = $raw;
			}
			else {
				\parse_str( (string)$raw, $formParams );
			}
		}

		return $formParams;
	}
}