<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\HookTimings;

class Processor extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Processor {

	protected function run() {
		$mod = self::con()->getModule_Integrations();

		$mod->getControllerMWP()->execute();

		if ( !self::con()->this_req->request_bypasses_all_restrictions ) {
			$mod->getController_SpamForms()->execute();

			add_action( 'init', function () use ( $mod ) {
				$mod->getController_UserForms()->execute();
			}, HookTimings::INIT_USER_FORMS_SETUP );
		}
	}
}