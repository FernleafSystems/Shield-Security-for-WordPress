<?php declare( strict_types=1 );

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Comms;

class ICWP_WPSF_FeatureHandler_Comms extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	public function getSureSendController() :Comms\Lib\SureSend\SureSendController {
		return ( new Comms\Lib\SureSend\SureSendController() )
			->setMod( $this );
	}

	protected function getNamespaceBase() :string {
		return 'Comms';
	}
}