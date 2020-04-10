<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class HandshakingNonce {

	use ModConsumer;

	/**
	 * @return string
	 */
	public function create() {
		$aNonces = $this->getNonces();

		$sPass = wp_generate_password( 12, false );
		$aNonces[ $sPass ] = Services::Request()->ts() + 90;
		$this->storeNonces( $aNonces );

		return $sPass;
	}

	/**
	 * @param string $sNonce
	 * @return bool
	 */
	public function verify( $sNonce ) {
		$aNs = $this->getNonces();
		$bValid = false;
		if ( isset( $aNs[ $sNonce ] ) ) {
			$bValid = Services::Request()->ts() < $aNs[ $sNonce ];
			unset( $aNs[ $sNonce ] );
			$this->storeNonces( $aNs );
		}
		return $bValid;
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
	 * @param int[] $aNonces
	 * @return $this
	 */
	private function storeNonces( array $aNonces ) {
		$oSnapiCon = $this->getCon()
						  ->getModule_Plugin()
						  ->getShieldNetApiController();
		$oSnapiCon->vo->nonces = array_filter(
			$aNonces,
			function ( $nTS ) {
				return $nTS > Services::Request()->ts();
			}
		);
		$oSnapiCon->storeVoData();
		return $this;
	}
}
