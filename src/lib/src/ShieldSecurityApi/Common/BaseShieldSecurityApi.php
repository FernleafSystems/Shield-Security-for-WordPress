<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldSecurityApi\Common;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldSecurityApi\HandshakingNonce;
use FernleafSystems\Wordpress\Services\Services;

class BaseShieldSecurityApi extends BaseApi {

	use ModConsumer;
	const DEFAULT_URL_STUB = 'https://onedollarplugin.com/wp-json/apto-ssapi/v1';

	/**
	 * @param string $sProperty
	 * @return mixed
	 */
	public function __get( $sProperty ) {

		$mValue = parent::__get( $sProperty );

		switch ( $sProperty ) {

			case 'params_query':
				if ( $this->request_method == 'get' ) {
					if ( !is_array( $mValue ) ) {
						$mValue = [];
					}
					$mValue = array_merge(
						$this->getBaseShieldApiParams(),
						$mValue
					);
				}
				break;

			case 'params_body':
				if ( $this->request_method == 'post' ) {
					if ( !is_array( $mValue ) ) {
						$mValue = [];
					}
					$mValue = array_merge(
						$this->getBaseShieldApiParams(),
						$mValue
					);
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
	protected function getBaseShieldApiParams() {
		return [
			'url'        => Services::WpGeneral()->getHomeUrl( '', true ),
			'install_id' => $this->getCon()->getSiteInstallationId(),
			'nonce'      => ( new HandshakingNonce() )->setMod( $this->getMod() )->create(),
		];
	}
}