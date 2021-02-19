<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\HandshakingNonce;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class BaseShieldNetApi
 * @package FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common
 * @property array $shield_net_params
 */
class BaseShieldNetApi extends BaseApi {

	use ModConsumer;
	const DEFAULT_URL_STUB = 'https://net.getshieldsecurity.com/wp-json/apto-snapi/v1';

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function __get( string $key ) {

		$mValue = parent::__get( $key );

		switch ( $key ) {

			case 'params_query':
				if ( $this->request_method == 'get' ) {
					$mValue = array_merge( $this->shield_net_params, $mValue );
				}
				break;

			case 'params_body':
				if ( $this->request_method == 'post' ) {
					$mValue = array_merge( $this->shield_net_params, $mValue );
				}
				break;

			case 'shield_net_params':
				if ( !is_array( $mValue ) ) {
					$mValue = $this->getShieldNetApiParams();
					$this->shield_net_params = $mValue;
				}
				break;

			default:
				break;
		}

		return $mValue;
	}

	/**
	 * @return string[]
	 */
	protected function getShieldNetApiParams() {
		return [
			'url'        => Services::WpGeneral()->getHomeUrl( '', true ),
			'install_id' => $this->getCon()->getSiteInstallationId(),
			'nonce'      => ( new HandshakingNonce() )->setMod( $this->getMod() )->create(),
		];
	}
}