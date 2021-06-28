<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\WPHashes;

use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common;
use FernleafSystems\Wordpress\Services\Services;

class SolicitToken extends Common\BaseShieldNetApi {

	const API_ACTION = 'wphashes/token';

	public function send() :array {
		$this->shield_net_params_required = false;
		$this->params_query = [
			'url' => Services::WpGeneral()->getHomeUrl()
		];
		$raw = $this->sendReq();
		return ( !empty( $raw ) && is_array( $raw[ 'data' ] ) ) ? $raw[ 'data' ] : [];
	}

	protected function getApiRequestUrl() :string {
		return sprintf( '%s/%s', parent::getApiRequestUrl(), $this->getCon()->getSiteInstallationId() );
	}
}