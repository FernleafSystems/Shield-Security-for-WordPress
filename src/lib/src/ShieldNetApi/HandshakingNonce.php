<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class HandshakingNonce {

	use ModConsumer;

	public function create() :string {
		$nonces = $this->getNonces();

		$pass = wp_generate_password( 12, false );
		$nonces[ $pass ] = Services::Request()->ts() + 90;
		$this->storeNonces( $nonces );

		return $pass;
	}

	public function verify( string $nonce ) :bool {
		$nonces = $this->getNonces();
		$valid = false;
		if ( isset( $nonces[ $nonce ] ) ) {
			$valid = Services::Request()->ts() <= $nonces[ $nonce ];
			unset( $nonces[ $nonce ] );
			$this->storeNonces( $nonces );
		}
		return $valid;
	}

	/**
	 * @return int[]
	 */
	private function getNonces() {
		return $this->getCon()
					->getModule_Plugin()
					->getShieldNetApiController()->vo->nonces;
	}

	/**
	 * Also filters out expired nonces on-save
	 * @param int[] $nonces
	 * @return $this
	 */
	private function storeNonces( array $nonces ) {
		$snapiCon = $this->getCon()
						 ->getModule_Plugin()
						 ->getShieldNetApiController();
		$snapiCon->vo->nonces = array_filter(
			$nonces,
			function ( $ts ) {
				return $ts > Services::Request()->ts();
			}
		);
		$snapiCon->storeVoData();
		return $this;
	}
}
