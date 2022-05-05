<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Processor extends BaseShield\Processor {

	public function onWpInit() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$mod->getSecurityAdminController()->execute();
		$mod->getWhiteLabelController()->execute();
	}
}