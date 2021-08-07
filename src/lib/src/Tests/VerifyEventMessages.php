<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class VerifyEventMessages {

	use PluginControllerConsumer;

	public function run() {
		$con = $this->getCon();

		$evts = $con->loadEventsService()->getEvents();
		var_dump( $evts );
	}
}