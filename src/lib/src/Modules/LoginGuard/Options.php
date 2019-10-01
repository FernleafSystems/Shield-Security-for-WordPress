<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

/**
 * Class Options
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard
 */
class Options extends Base\ShieldOptions {

	/**
	 * @return bool
	 */
	public function isCooldownEnabled() {
		return $this->getCooldownInterval() > 0;
	}

	/**
	 * @return int
	 */
	public function getCooldownInterval() {
		return (int)$this->getOpt( 'login_limit_interval' );
	}
}