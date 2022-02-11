<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Rest\Request\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;

class Start extends Base {

	protected function process() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		if ( $this->getScansStatus()[ 'enqueued_count' ] > 0 ) {
			throw new \Exception( 'Scans are already running.' );
		}
		$mod->getScansCon()->startAllScans();
		return [
			'scans_started' => $this->getScansStatus()[ 'enqueued_count' ] > 0
		];
	}
}