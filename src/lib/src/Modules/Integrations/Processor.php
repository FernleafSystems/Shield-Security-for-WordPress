<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Processor extends BaseShield\Processor {

	public function run() {
		$this->getCon()
			 ->getModule_Integrations()
			 ->getControllerMWP()
			 ->run();
	}
}