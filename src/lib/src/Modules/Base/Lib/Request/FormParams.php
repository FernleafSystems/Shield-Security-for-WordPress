<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Request;

use FernleafSystems\Wordpress\Services\Services;

class FormParams {

	public const ENC_NONE = 'none';
	public const ENC_LZ = 'lz-string';
	public const ENC_BASE64 = 'b64';

	public static function Retrieve( string $encoding = self::ENC_NONE ) :array {
		$req = Services::Request();
		$formParams = [];
		$raw = $req->post( 'form_params', '' );

		if ( empty( $raw ) ) {
			$formParams = $req->post;
		}
		else {
			$maybeEncoding = $req->post( 'enc_params' );
			if ( in_array( $maybeEncoding, [ 'none', 'lz-string', 'b64' ] ) ) {
				$encoding = $maybeEncoding;
			}

			switch ( $encoding ) {
				case 'lz-string':
					$raw = \LZCompressor\LZString::decompress( base64_decode( $raw ) );
					break;

				case 'b64':
					$raw = base64_decode( $raw );
					break;

				case 'none':
				default:
					if ( empty( $raw ) ) {
						$raw = '';
					}
					break;
			}

			parse_str( (string)$raw, $formParams );
		}

		return is_array( $formParams ) ? $formParams : [];
	}
}