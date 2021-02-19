<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class Processor extends BaseShield\Processor {

	protected function run() {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		// XML-RPC Compatibility
		if ( Services::WpGeneral()->isXmlrpc() && $mod->isXmlrpcBypass() ) {
			return;
		}

		( new Lib\Rename\RenameLogin() )
			->setMod( $mod )
			->execute();

		if ( !$mod->isVisitorWhitelisted() ) {

			add_action( 'init', function () {
				$this->launchAntiBot();
			}, -100 );

			$mod->getLoginIntentController()->run();
		}
	}

	private function launchAntiBot() {
		( new Lib\AntiBot\AntibotSetup() )
			->setMod( $this->getMod() )
			->execute();
	}
}