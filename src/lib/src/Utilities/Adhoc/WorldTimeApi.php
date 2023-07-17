<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Adhoc;

use FernleafSystems\Wordpress\Services\Services;

class WorldTimeApi {

	/**
	 * @throws \Exception
	 */
	public function current() :int {
		$raw = Services::HttpRequest()->getContent( 'https://api.aptoweb.com/api/v1/time' );
		if ( empty( $raw ) ) {
			throw new \Exception( 'Request to World Clock Api Failed' );
		}
		$dec = \json_decode( $raw, true );
		if ( empty( $dec ) ) {
			throw new \Exception( 'Failed to decode World Clock Api response' );
		}
		return (int)$dec[ 'current' ][ 'seconds' ];
	}

	/**
	 * @throws \Exception
	 */
	public function diffServerWithReal() :int {
		return (int)\abs( \time() - $this->current() );
	}
}
