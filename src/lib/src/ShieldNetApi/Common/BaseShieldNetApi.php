<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\InstallationID;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\HandshakingNonce;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property array  $shield_net_params
 * @property bool   $shield_net_params_required
 * @property string $last_error
 */
class BaseShieldNetApi extends BaseApi {

	use PluginControllerConsumer;

	public const DEFAULT_URL_STUB = 'https://net.getshieldsecurity.com/wp-json/apto-snapi';

	/**
	 * @return mixed
	 */
	public function __get( string $key ) {

		$value = parent::__get( $key );

		switch ( $key ) {

			case 'params_query':
				if ( $this->request_method == 'get' ) {
					$value = \array_merge( $this->shield_net_params, $value );
				}
				$lastError = $this->last_error;
				if ( !empty( $lastError ) ) {
					$value[ 'last_error' ] = $lastError;
				}
				break;

			case 'params_body':
				if ( $this->request_method == 'post' ) {
					$value = \array_merge( $this->shield_net_params, $value );
				}

				$lastError = $this->last_error;
				if ( !empty( $lastError ) ) {
					$value[ 'last_error' ] = $lastError;
				}
				break;

			case 'shield_net_params':
				if ( !\is_array( $value ) ) {
					$value = $this->getShieldNetApiParams();
				}
				break;

			case 'shield_net_params_required':
				$value = \is_null( $value ) || $value;
				break;

			default:
				break;
		}

		return $value;
	}

	/**
	 * @return string[]
	 */
	protected function getShieldNetApiParams() :array {
		return $this->shield_net_params_required ? [
			'url'        => Services::WpGeneral()->getHomeUrl( '', true ),
			'install_id' => ( new InstallationID() )->id(),
			'nonce'      => ( new HandshakingNonce() )->create(),
		] : [];
	}
}