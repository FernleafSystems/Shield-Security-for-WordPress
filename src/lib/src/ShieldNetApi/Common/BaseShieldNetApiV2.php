<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common;

use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\HandshakingNonce;
use FernleafSystems\Wordpress\Services\Services;

class BaseShieldNetApiV2 extends BaseShieldNetApi {

	const DEFAULT_API_VERSION = '2';

	/**
	 * @return string[]
	 */
	protected function getShieldNetApiParams() :array {
		$con = $this->getCon();
		return ( $this->shield_net_params_required || $con->isPremiumActive() ) ? [
			'url'        => Services::WpGeneral()->getHomeUrl( '', true ),
			'install_id' => $con->getSiteInstallationId(),
			'nonce'      => ( new HandshakingNonce() )->setCon( $con )->create(),
		] : [];
	}
}