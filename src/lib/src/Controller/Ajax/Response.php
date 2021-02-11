<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Ajax;

use FernleafSystems\Wordpress\Services\Services;

class Response {

	public function issue( array $response, $wrap = false ) {
		$wrap = $wrap || (bool)Services::Request()->request( 'apto_wrap_response' );

		if ( !headers_sent() ) {
			header( 'Content-Type: application/json; charset='.get_option( 'blog_charset' ) );
			nocache_headers();
			if ( isset( $response[ 'status_code' ] ) ) {
				status_header( $response[ 'status_code' ] );
			}
		}
		if ( $wrap ) {
			echo '##APTO_OPEN##';
		}
		echo wp_json_encode( $response );
		if ( $wrap ) {
			echo '##APTO_CLOSE##';
		}
		die( '' );
	}
}