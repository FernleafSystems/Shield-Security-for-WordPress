<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldSecurityApi\Common;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldSecurityApi\HandshakingNonce;
use FernleafSystems\Wordpress\Services\Services;

class BaseShieldSecurityApi extends BaseApi {

	use ModConsumer;
	const DEFAULT_URL_STUB = 'https://onedollarplugin.com/wp-json/apto-ssapi/v1';

	/**
	 * @return string[]
	 */
	protected function getBaseParams() {
		return [
			'url'        => Services::WpGeneral()->getHomeUrl( '', true ),
			'install_id' => $this->getCon()->getSiteInstallationId(),
			'nonce'      => ( new HandshakingNonce() )->setMod( $this->getMod() )->create(),
		];
	}
}