<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

class ProcessOffenses extends ExecOnceModConsumer {

	protected function canRun() :bool {
		return $this->getCon()->this_req->ip_is_public;
	}

	protected function run() {
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();

		$mod->loadOffenseTracker()->setIfCommit( true );

		$con = $this->getCon();
		add_action( $con->prefix( 'pre_plugin_shutdown' ), function () {
			$this->processOffense();
		} );
	}

	private function processOffense() {
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();

		$tracker = $mod->loadOffenseTracker();
		if ( !$this->getCon()->plugin_deleting && $tracker->hasVisitorOffended() && $tracker->isCommit() ) {
			( new IPs\Components\ProcessOffense() )
				->setMod( $mod )
				->setIp( $this->getCon()->this_req->ip )
				->run();
		}
	}
}