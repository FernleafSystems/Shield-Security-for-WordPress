<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Rest\Request;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Rest\Request\Process;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;

class Start extends Process {

	protected function process() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$queueCon = $mod->getScanQueueController();
		if ( $queueCon->hasRunningScans() ) {
			throw new \Exception( 'Scans are already currently running.' );
		}

		$mod->getScansCon()->startAllScans();

		return [
			'scans_started' => $queueCon->hasRunningScans()
		];
	}
}