<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\HookTimings;

class Processor extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Processor {

	protected function run() {
		$mod = self::con()->getModule_LoginGuard();

		// XML-RPC Compatibility
		if ( self::con()->this_req->wp_is_xmlrpc && $mod->isXmlrpcBypass() ) {
			return;
		}

		( new Lib\Rename\RenameLogin() )->execute();

		$mod->getMfaController()->execute();

		add_action( 'init', function () {
			( new Lib\AntiBot\AntibotSetup() )->execute();
		}, HookTimings::INIT_ANTIBOT_SETUP );
	}
}