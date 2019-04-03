<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\BotTrap;

use FernleafSystems\Wordpress\Services\Services;

class Detect404 extends Base {

	const OPT_KEY = 'track_404';

	protected function process() {
		add_action( 'template_redirect', function () {
			if ( is_404() ) {
				$this->doTransgression();
			}
		} );
	}

	protected function isTransgression() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oFO */
		$oFO = $this->getMod();
		return $oFO->isTransgression404();
	}

	/**
	 * @return $this
	 */
	protected function writeAudit() {
		$this->createNewAudit(
			'wpsf',
			sprintf( _wpsf__( '404 detected at "%s"' ), Services::Request()->getPath() ),
			2, 'bottrap_404'
		);
		return $this;
	}
}
