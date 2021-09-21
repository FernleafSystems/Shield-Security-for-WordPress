<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;

class Debug extends Modules\Base\Debug {

	public function run() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$mod->getReportingController()->runHourlyCron();
		die( 'finish' );
	}
}