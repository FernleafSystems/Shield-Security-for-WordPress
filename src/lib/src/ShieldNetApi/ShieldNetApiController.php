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
	}

	/**
	 * Automatically throttles request because otherwise PRO-nulled versions of Shield will cause
	 * overload on our API.
	 *
	 * Note To Plugin 'Null'ers:
	 * PRO features that require handshaking wont work even if you null the plugin because our
	 * API will always reject those requests. Don't fiddle with this function, please.  You may get
	 * away with nulling the plugin for many PRO features, but you can't null our API, sorry.
	 * @return bool
	 */
	public function canHandshake() {
		$nNow = Services::Request()->ts();
		if ( $this->vo->last_handshake_at === 0 ) {

			$bCanTry = $nNow - MINUTE_IN_SECONDS*5*$this->vo->handshake_fail_count
					   > $this->vo->last_handshake_attempt_at;
			if ( $bCanTry ) {
				$bCanHandshake = ( new ShieldNetApi\Handshake\Verify() )
					->setMod( $this->getMod() )
					->run();

				if ( $bCanHandshake ) {
					$this->vo->last_handshake_at = $nNow;
					$this->vo->handshake_fail_count = 0;
				}
				else {
					$this->vo->handshake_fail_count++;
				}
				$this->vo->last_handshake_attempt_at = $nNow;
				$this->storeVoData();
			}
		}

		return $this->vo->last_handshake_at > 0;
	}

	public function storeVoData() {
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
		$oOpts = $this->getOptions();

		$mValue = $this->__adapterGet( $sProperty );

		switch ( $sProperty ) {

			case 'vo':
				if ( empty( $mValue ) ) {
					$aData = $oOpts->getOpt( 'snapi_data', [] );
					$mValue = ( new ShieldNetApiDataVO() )->applyFromArray(
						is_array( $aData ) ? $aData : []
					);
					$this->vo = $mValue;
				}
				break;

			default:
				break;
		}

		return $mValue;
	}
}
