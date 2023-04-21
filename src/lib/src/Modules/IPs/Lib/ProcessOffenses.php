<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

class ProcessOffenses  {

	use ExecOnce;
	use IPs\ModConsumer;

	protected function canRun() :bool {
		return $this->getCon()->this_req->ip_is_public;
	}

	protected function run() {
		$this->mod()->loadOffenseTracker()->setIfCommit( true );
		add_action( $this->con()->prefix( 'pre_plugin_shutdown' ), function () {
			$this->processOffense();
		} );
	}

	private function processOffense() {
		$mod = $this->getCon()->getModule_IPs();

		$tracker = $mod->loadOffenseTracker();
		if ( !$this->getCon()->plugin_deleting && $tracker->hasVisitorOffended() && $tracker->isCommit() ) {
			( new IPs\Components\ProcessOffense() )
				->setMod( $mod )
				->setIp( $this->getCon()->this_req->ip )
				->execute();
		}
	}
}