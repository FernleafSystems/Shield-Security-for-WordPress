<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\ScansStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

abstract class ScanBase extends Base {

	use PluginControllerConsumer;

	protected function getScansStatus() :array {
		$current = ( new ScansStatus() )->current();
		$currentScan = __( 'No scan running.', 'wp-simple-firewall' );
		if ( !empty( $current ) ) {
			$currentScan = self::con()->comps->scans->getScanCon( $current )->getScanName();
		}
		$queueCon = self::con()->comps->scans_queue;
		return [
			'enqueued_count'  => \count( ( new ScansStatus() )->enqueued() ),
			'enqueued_status' => $queueCon->getScansRunningStates(),
			'current_slug'    => $current,
			'current_name'    => $currentScan,
			'progress'        => $queueCon->getScanJobProgress(),
		];
	}
}