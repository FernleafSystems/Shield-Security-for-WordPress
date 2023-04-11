<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Wordpress\Plugin\Shield;

/**
 * @deprecated 17.1
 */
trait MfaControllerConsumer {

	/**
	 * @var MfaController
	 */
	private $oMfaController;

	/**
	 * @return MfaController
	 */
	public function getMfaCon() {
		return $this->oMfaController;
	}

	/**
	 * @return $this
	 */
	public function setMfaController( MfaController $con ) {
		$this->oMfaController = $con;
		return $this;
	}
}