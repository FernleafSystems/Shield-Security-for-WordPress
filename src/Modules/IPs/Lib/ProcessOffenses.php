<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\ProcessOffense;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class ProcessOffenses {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function canRun() :bool {
		return self::con()->this_req->ip_is_public;
	}

	protected function run() {
		self::con()->comps->offense_tracker->setIfCommit( true );
		add_action( self::con()->prefix( 'pre_plugin_shutdown' ), function () {
			if ( !self::con()->plugin_deleting
				 && self::con()->comps->offense_tracker->hasVisitorOffended()
				 && self::con()->comps->offense_tracker->isCommit() ) {
				( new ProcessOffense() )
					->setIp( self::con()->this_req->ip )
					->execute();
			}
		} );
	}
}