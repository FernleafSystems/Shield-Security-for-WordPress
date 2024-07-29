<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\WPHashes;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\InstallationID;
use FernleafSystems\Wordpress\Services\Services;

class SolicitToken extends \FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common\BaseShieldNetApi {

	public const API_ACTION = 'wphashes/token';

	public function send() :array {
		$this->shield_net_params_required = false;
		$this->api_version = '2';
		$this->params_query = [
			'url' => Services::WpGeneral()->getHomeUrl()
		];
		$raw = $this->sendReq();
		return ( !empty( $raw ) && \is_array( $raw ) && !empty( $raw[ 'token' ] ) ) ? $raw : [];
	}

	protected function getApiRequestUrl() :string {
		return sprintf( '%s/%s', parent::getApiRequestUrl(), ( new InstallationID() )->id() );
	}
}