<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Queues;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\ModConsumer;
use FernleafSystems\Wordpress\Services\Utilities\BackgroundProcessing\BackgroundProcess;

class SnapshotUpdate extends BackgroundProcess {

	use ModConsumer;

	protected function task( $item ) {
		$auditCon = $this->mod()->getAuditCon();
		$auditCon->runSnapshotDiscovery( $auditCon->getAuditors()[ $item ] );
		return false;
	}
}