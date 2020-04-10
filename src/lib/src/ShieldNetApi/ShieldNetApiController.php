<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi;

use FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class ShieldNetApiController
 * @package FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi
 * @property ShieldNetApiDataVO $vo
 */
class ShieldNetApiController {

	use ModConsumer;
	use StdClassAdapter {
		__get as __adapterGet;
		__set as __adapterSet;
	}

	/**
	 * @return bool
	 */
	public function canHandshake() {
		$nNow = Services::Request()->ts();
		if ( empty( $this->vo->last_handshake_at )
			 && $nNow - MINUTE_IN_SECONDS*5 > (int)$this->vo->last_handshake_attempt_at ) {
			$bCanHandshake = ( new ShieldNetApi\Handshake\Verify() )
				->setMod( $this->getMod() )
				->run();

			$this->vo->last_handshake_attempt_at = $nNow;
			if ( $bCanHandshake ) {
				$this->vo->last_handshake_at = $nNow;
			}
			$this->storeVoData();
		}

		return $this->vo->last_handshake_at > 0;
	}

	private function storeVoData() {
		$oMod = $this->getMod();
		$oMod->getOptions()->setOpt( 'snapi_data', $this->vo->getRawDataAsArray() );
		$oMod->saveModOptions();
	}

	/**
	 * @param string $sProperty
	 * @return mixed
	 */
	public function __get( $sProperty ) {
		/** @var Plugin\Options $oOpts */
		$oOpts = $this->getCon()
					  ->getModule_Plugin()
					  ->getOptions();

		$mValue = $this->__adapterGet( $sProperty );

		switch ( $sProperty ) {

			case 'vo':
				if ( empty( $mValue ) ) {
					$mValue = ( new ShieldNetApiDataVO() )->applyFromArray( $oOpts->getShieldNetApiData() );
				}
				break;

			default:
				break;
		}

		return $mValue;
	}

	/**
	 * @param string $sProperty
	 * @param mixed  $mValue
	 * @return $this|mixed
	 */
	public function __set( $sProperty, $mValue ) {
		$this->__adapterSet( $sProperty, $mValue );
		$this->storeVoData(); // ensure it's save when we update the VO
		return;
	}
}
