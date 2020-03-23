<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldSecurityApi;

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
		$aNonces[ $sPass ] = Services::Request()->carbon()->addSeconds( 90 )->timestamp;
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
		$aNonces = $this->getCon()
						->getModule_Plugin()
						->getOptions()
						->getOpt( 'ssapi_nonces', [] );
		return is_array( $aNonces ) ? $aNonces : [];
	}

	/**
	 * Also filters out expired nonces on-save
	 * @param int[] $aNonces
	 * @return $this
	 */
	private function storeNonces( array $aNonces ) {
		$oModPlugin = $this->getCon()->getModule_Plugin();
		$oModPlugin->getOptions()
				   ->setOpt( 'ssapi_nonces',
					   array_filter(
						   $aNonces,
						   function ( $nTS ) {
							   return $nTS > Services::Request()->ts();
						   }
					   )
				   );
		$oModPlugin->saveModOptions();
		return $this;
	}
}
