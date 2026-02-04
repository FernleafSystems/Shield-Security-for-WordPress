<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class HandshakingNonce {

	use PluginControllerConsumer;

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
	private function getNonces() :array {
		return self::con()->comps->shieldnet->vo->nonces;
	}

	/**
	 * Also filters out expired nonces on-save
	 * @param int[] $nonces
	 */
	private function storeNonces( array $nonces ) {
		self::con()->comps->shieldnet->vo->nonces = \array_filter(
			$nonces,
			function ( $ts ) {
				return $ts > Services::Request()->ts();
			}
		);
		self::con()->comps->shieldnet->storeVoData();
	}
}
