<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Options extends BaseShield\Options {

	public function getCustomWhitelist() :array {
		$aW = $this->getOpt( 'page_params_whitelist', [] );
		return is_array( $aW ) ? $aW : [];
	}

	public function isIgnoreAdmin() :bool {
		return $this->isOpt( 'whitelist_admins', 'Y' );
	}

	public function isSendBlockEmail() :bool {
		return $this->isOpt( 'block_send_email', 'Y' );
	}
}