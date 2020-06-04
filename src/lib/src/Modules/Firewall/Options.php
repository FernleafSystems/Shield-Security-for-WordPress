<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Options extends Base\ShieldOptions {

	/**
	 * @return array
	 */
	public function getCustomWhitelist() {
		$aW = $this->getOpt( 'page_params_whitelist', [] );
		return is_array( $aW ) ? $aW : [];
	}

	/**
	 * @return bool
	 */
	public function isIgnoreAdmin() {
		return $this->isOpt( 'whitelist_admins', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isSendBlockEmail() {
		return $this->isOpt( 'block_send_email', 'Y' );
	}
}