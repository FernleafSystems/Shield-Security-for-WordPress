<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\SnapshotVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Build {

	use ModConsumer;

	/**
	 * @throws \Exception
	 */
	public function run( string $slug ) :SnapshotVO {
		$snapper = $this->mod()
						->getAuditCon()
						->getAuditors()[ $slug ]->getSnapper();
		$snapshot = new SnapshotVO();
		$snapshot->slug = $slug;
		$snapshot->data = ( new $snapper() )->snap();
		$snapshot->snapshot_at = Services::Request()->ts();
		return $snapshot;
	}
}
