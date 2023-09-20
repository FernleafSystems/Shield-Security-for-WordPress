<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool;

use FernleafSystems\Wordpress\Services\Services;

class ConvertHtmlToPDF {

	/**
	 * @throws \Exception
	 */
	public function run( string $content ) :string {
		$req = Services::HttpRequest();
		$req->post( 'https://wphashes.com/api/apto-wphashes/v2/convert/html/pdf', [
			'body' => [
				'source_data' => $content,
			]
		] );
		$res = @\json_decode( $req->lastResponse->body, true );
		if ( !\is_array( $res ) || empty( $res[ 'converted_content' ] ) ) {
			throw new \Exception( 'Could not convert' );
		}
		return empty( $res[ 'is_base64' ] ) ? $res[ 'converted_content' ] : \base64_decode( $res[ 'converted_content' ] );
	}
}