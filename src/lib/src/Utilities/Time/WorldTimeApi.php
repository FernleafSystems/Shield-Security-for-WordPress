<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Time;

use FernleafSystems\Wordpress\Services\Services;

class WorldTimeApi {

	/**
	 * @return int
	 * @throws \Exception
	 */
	public function current() :int {
		$raw = Services::HttpRequest()
					   ->getContent( 'https://showcase.api.linx.twenty57.net/UnixTime/tounixtimestamp?datetime=now' );
		if ( empty( $raw ) ) {
			throw new \Exception( 'Request to World Clock Api Failed' );
		}
		$dec = json_decode( $raw, true );
		if ( empty( $dec ) ) {
			throw new \Exception( 'Failed to decode World Clock Api response' );
		}
		return (int)$dec[ 'UnixTimeStamp' ];
	}

	/**
	 * @return int
	 * @throws \Exception
	 */
	public function diffServerWithReal() :int {
		return (int)abs( time() - $this->current() );
	}
}
