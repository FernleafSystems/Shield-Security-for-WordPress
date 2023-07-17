<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Rest\Request;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Rest\Request\Process;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\ScansStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Strings;

abstract class Base extends Process {

	use ModConsumer;

	protected function newReqVO() {
		return new RequestVO();
	}

	protected function getScansStatus() :array {
		/** @var Strings $strings */
		$strings = $this->mod()->getStrings();
		$queueCon = $this->mod()->getScanQueueController();

		$current = ( new ScansStatus() )->current();
		$hasCurrent = !empty( $current );
		if ( $hasCurrent ) {
			$currentScan = $strings->getScanName( $current );
		}
		else {
			$currentScan = __( 'No scan running.', 'wp-simple-firewall' );
		}

		$enqueued = ( new ScansStatus() )->enqueued();

		return [
			'enqueued_count'  => \count( $enqueued ),
			'enqueued_status' => $queueCon->getScansRunningStates(),
			'current_slug'    => $current,
			'current_name'    => $currentScan,
			'progress'        => $queueCon->getScanJobProgress(),
		];
	}
}