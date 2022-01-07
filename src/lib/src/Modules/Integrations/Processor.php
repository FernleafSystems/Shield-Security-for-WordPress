<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class Processor extends BaseShield\Processor {

	protected function run() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$mod->getControllerMWP()->execute();

		if ( !empty( Services::IP()->getRequestIp() ) ) {
			$mod->getController_SpamForms()->execute();

			add_action( 'init', function () use ( $mod ) {
				$mod->getController_UserForms()->execute();
			}, -100 );
		}
	}
}