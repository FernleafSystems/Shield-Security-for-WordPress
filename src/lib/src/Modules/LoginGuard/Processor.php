<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\HookTimings;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Processor extends BaseShield\Processor {

	protected function run() {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		// XML-RPC Compatibility
		if ( $this->getCon()->this_req->wp_is_xmlrpc && $mod->isXmlrpcBypass() ) {
			return;
		}

		( new Lib\Rename\RenameLogin() )
			->setMod( $mod )
			->execute();

		$mod->getMfaController()->execute();

		add_action( 'init', function () {
			( new Lib\AntiBot\AntibotSetup() )
				->setMod( $this->getMod() )
				->execute();
		}, HookTimings::INIT_ANTIBOT_SETUP );
	}
}