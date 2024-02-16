<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\HookTimings;

class Processor extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Processor {

	protected function run() {
		$con = self::con();

		// XML-RPC Compatibility
		if ( $con->this_req->wp_is_xmlrpc && $con->getModule_Plugin()->isXmlrpcBypass() ) {
			return;
		}

		( new Lib\Rename\RenameLogin() )->execute();

		$con->getModule_LoginGuard()->getMfaController()->execute();

		add_action( 'init', function () {
			( new Lib\AntiBot\AntibotSetup() )->execute();
		}, HookTimings::INIT_ANTIBOT_SETUP );
	}
}