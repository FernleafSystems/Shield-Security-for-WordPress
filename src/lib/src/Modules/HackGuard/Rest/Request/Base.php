<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Rest\Request;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Rest\Request\Process;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\ScansStatus;

abstract class Base extends Process {

	use ModConsumer;

	protected function newReqVO() {
		return new RequestVO();
	}

	protected function getScansStatus() :array {

		$current = ( new ScansStatus() )->current();
		$currentScan = __( 'No scan running.', 'wp-simple-firewall' );
		if ( !empty( $current ) ) {
			$currentScan = $this->mod()
								->getScansCon()
								->getScanCon( $current )
								->getScanName();
		}

		$queueCon = $this->mod()->getScanQueueController();
		return [
			'enqueued_count'  => \count( ( new ScansStatus() )->enqueued() ),
			'enqueued_status' => $queueCon->getScansRunningStates(),
			'current_slug'    => $current,
			'current_name'    => $currentScan,
			'progress'        => $queueCon->getScanJobProgress(),
		];
	}
}