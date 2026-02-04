<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Forms;

use FernleafSystems\Wordpress\Services\Services;

class FormParams {

	public const ENC_NONE = 'none';
	public const ENC_BASE64 = 'b64';
	public const ENC_JSON = 'json';
	public const ENC_OBSCURE = 'obscure';

	public static function Retrieve() :array {
		$req = Services::Request();
		$formParams = [];
		$raw = $req->post( 'form_params', '' );

		if ( empty( $raw ) ) {
			$formParams = $req->post;
		}
		elseif ( \is_array( $raw ) ) {
			$formParams = $raw;
		}
		else {
			$formEnc = $req->post( 'form_enc' );
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

		return \is_array( $formParams ) ? $formParams : [];
	}
}