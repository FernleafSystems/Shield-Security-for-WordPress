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

		$value = parent::__get( $key );

		switch ( $key ) {

			case 'params_query':
				if ( $this->request_method == 'get' ) {
					$value = array_merge( $this->shield_net_params, $value );
				}
				break;

			case 'params_body':
				if ( $this->request_method == 'post' ) {
					$value = array_merge( $this->shield_net_params, $value );
				}
				break;

			case 'shield_net_params':
				if ( !is_array( $value ) ) {
					$value = $this->getShieldNetApiParams();
					$this->shield_net_params = $value;
				}
				break;

			default:
				break;
		}

		return $value;
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