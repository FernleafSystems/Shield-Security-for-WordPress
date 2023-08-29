<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

class ProcessOffenses {

	use ExecOnce;
	use IPs\ModConsumer;

	protected function canRun() :bool {
		return self::con()->this_req->ip_is_public;
	}

	protected function run() {
		$this->mod()->loadOffenseTracker()->setIfCommit( true );
		add_action( self::con()->prefix( 'pre_plugin_shutdown' ), function () {
			$this->processOffense();
		} );
	}

	private function processOffense() {
		$mod = self::con()->getModule_IPs();

		$tracker = $mod->loadOffenseTracker();
		if ( !self::con()->plugin_deleting && $tracker->hasVisitorOffended() && $tracker->isCommit() ) {
			( new IPs\Components\ProcessOffense() )
				->setIp( self::con()->this_req->ip )
				->execute();
		}
	}
}