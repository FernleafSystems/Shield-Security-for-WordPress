<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Options extends Base\ShieldOptions {

	/**
	 * @return bool
	 */
	public function isSendBlockEmail() {
		return $this->isOpt( 'block_send_email', 'Y' );
	}
}