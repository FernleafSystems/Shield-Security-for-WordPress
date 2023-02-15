<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\HookTimings;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Processor extends BaseShield\Processor {

	protected function run() {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$mod->getControllerMWP()->execute();

		if ( !$this->getCon()->this_req->request_bypasses_all_restrictions ) {
			$mod->getController_SpamForms()->execute();

			add_action( 'init', function () use ( $mod ) {
				$mod->getController_UserForms()->execute();
			}, HookTimings::INIT_USER_FORMS_SETUP );
		}
	}
}