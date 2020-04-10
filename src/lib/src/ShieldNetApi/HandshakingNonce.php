<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;
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
		/** @var Plugin\Options $oOpts */
		$oOpts = $this->getCon()
					  ->getModule_Plugin()
					  ->getOptions();
		return $oOpts->getShieldNetApiData()[ 'nonces' ];
	}

	/**
	 * Also filters out expired nonces on-save
	 * @param int[] $aNonces
	 * @return $this
	 */
	private function storeNonces( array $aNonces ) {
		$oModPlugin = $this->getCon()->getModule_Plugin();
		/** @var Plugin\Options $oOpts */
		$oOpts = $oModPlugin->getOptions();

		$aD = $oOpts->getShieldNetApiData();
		$aD[ 'nonces' ] = array_filter(
			$aNonces,
			function ( $nTS ) {
				return $nTS > Services::Request()->ts();
			}
		);
		$oOpts->setOpt( 'snapi_data', $aD );

		$oModPlugin->saveModOptions();
		return $this;
	}
}
