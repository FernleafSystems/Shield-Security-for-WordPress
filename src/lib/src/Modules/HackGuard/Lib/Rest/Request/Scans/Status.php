<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Rest\Request\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\ScansStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Strings;

class Status extends Base {

	protected function process() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Strings $strings */
		$strings = $mod->getStrings();

		$statusChecker = ( new ScansStatus() )->setMod( $mod );
		$queueCon = $mod->getScanQueueController();

		$current = $statusChecker->current();
		$hasCurrent = !empty( $current );
		if ( $hasCurrent ) {
			$currentScan = $strings->getScanName( $current );
		}
		else {
			$currentScan = __( 'No scan running.', 'wp-simple-firewall' );
		}

		$enqueued = $statusChecker->enqueued();

		return [
			'enqueued_count'  => count( $enqueued ),
			'enqueued_status' => $queueCon->getScansRunningStates(),
			'current_slug'    => $current,
			'current_name'    => $currentScan,
			'progress'        => 100*$queueCon->getScanJobProgress(),
		];
	}
}