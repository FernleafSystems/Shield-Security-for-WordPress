<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\MouseTrap;

use FernleafSystems\Wordpress\Services\Services;

class Detect404 extends Base {

	protected function process() {
		add_action( 'template_redirect', array( $this, 'doTrack404' ) );
	}

	public function doTrack404() {
		/** @var \ICWP_WPSF_FeatureHandler_Mousetrap $oFO */
		$oFO = $this->getMod();
		if ( $oFO->isEnabled404() && is_404() && !$oFO->isVerifiedBot() ) {
			if ( $oFO->isTransgression404() ) {
				$oFO->setIpTransgressed();
			}
			else {
				$oFO->setIpBlocked();
			}

			$this->createNewAudit(
				'wpsf',
				sprintf( _wpsf__( '404 detected at "%s"' ), Services::Request()->getPath() ),
				2, 'mousetrap_404'
			);
		}
	}
}
