<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Processor extends BaseShield\Processor {

	public function run() {
		die( 'here1' );
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$mod->getReportingController()->execute();
	}
}