<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\InstallationID;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\HandshakingNonce;
use FernleafSystems\Wordpress\Services\Services;

class BaseShieldNetApiV2 extends BaseShieldNetApi {

	public const DEFAULT_API_VERSION = '2';

	/**
	 * @return string[]
	 */
	protected function getShieldNetApiParams() :array {
		return ( $this->shield_net_params_required || self::con()->isPremiumActive() ) ? [
			'url'        => Services::WpGeneral()->getHomeUrl( '', true ),
			'install_id' => ( new InstallationID() )->id(),
			'nonce'      => ( new HandshakingNonce() )->create(),
		] : [];
	}
}