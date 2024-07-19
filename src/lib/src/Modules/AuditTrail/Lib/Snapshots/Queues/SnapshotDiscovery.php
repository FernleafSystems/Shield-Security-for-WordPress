<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Queues;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Utilities\BackgroundProcessing\BackgroundProcess;

class SnapshotDiscovery extends BackgroundProcess {

	use PluginControllerConsumer;

	protected function task( $item ) {
		self::con()->comps->activity_log->runSnapshotDiscovery( self::con()->comps->activity_log->getAuditors()[ $item ] );
		return false;
	}
}