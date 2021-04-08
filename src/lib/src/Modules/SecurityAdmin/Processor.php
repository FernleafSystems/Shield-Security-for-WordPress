<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Processor extends BaseShield\Processor {

	protected function run() {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		/** @var Options $opts */
		$opts = $this->getOptions();
		if ( $opts->isEnabledWhitelabel() ) {
			$mod->getWhiteLabelController()->execute();
		}

		$mod->getSecurityAdminController()->execute();
	}
}