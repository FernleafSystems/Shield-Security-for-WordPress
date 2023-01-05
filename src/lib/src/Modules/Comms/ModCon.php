<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Comms;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class ModCon extends BaseShield\ModCon {

	public function getSureSendController() :Lib\SureSend\SureSendController {
		return ( new Lib\SureSend\SureSendController() )->setMod( $this );
	}

	public function isModOptEnabled() :bool {
		return true;
	}
}