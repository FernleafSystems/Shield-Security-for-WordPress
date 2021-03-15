<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class ShieldNetApiController
 * @package FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi
 * @property ShieldNetApiDataVO $vo
 */
class ShieldNetApiController extends DynPropertiesClass {

	use ModConsumer;

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

			$canAttempt = $nNow - MINUTE_IN_SECONDS*5*$this->vo->handshake_fail_count
					   > $this->vo->last_handshake_attempt_at;
			if ( $canAttempt ) {
				$handshakeSuccess = ( new ShieldNetApi\Handshake\Verify() )
					->setMod( $this->getMod() )
					->run();

				if ( $handshakeSuccess ) {
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
		$this->getOptions()->setOpt( 'snapi_data', $this->vo->getRawData() );
		$this->getMod()->saveModOptions();
	}

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function __get( string $key ) {
		/** @var Plugin\Options $opts */
		$opts = $this->getOptions();

		$value = parent::__get( $key );

		switch ( $key ) {

			case 'vo':
				if ( empty( $value ) ) {
					$data = $opts->getOpt( 'snapi_data', [] );
					$value = ( new ShieldNetApiDataVO() )->applyFromArray(
						is_array( $data ) ? $data : []
					);
					$this->vo = $value;
				}
				break;

			default:
				break;
		}

		return $value;
	}
}
