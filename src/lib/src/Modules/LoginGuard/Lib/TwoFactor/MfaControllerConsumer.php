<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Wordpress\Plugin\Shield;

/**
 * @deprecated 17.0
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
	 * @param MfaController $oCon
	 * @return $this
	 */
	public function setMfaController( MfaController $oCon ) {
		$this->oMfaController = $oCon;
		return $this;
	}
}