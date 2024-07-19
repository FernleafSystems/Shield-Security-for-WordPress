<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\SnapshotVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Build {

	use PluginControllerConsumer;

	/**
	 * @throws \Exception
	 */
	public function run( string $slug ) :SnapshotVO {
		if ( !is_main_network() || !is_main_site() ) {
			throw new \Exception( 'Snapshots currently only run for the main site.' );
		}
		$snapper = self::con()->comps->activity_log->getAuditors()[ $slug ]->getSnapper();
		$snapshot = new SnapshotVO();
		$snapshot->slug = $slug;
		$snapshot->data = ( new $snapper() )->snap();
		$snapshot->snapshot_at = Services::Request()->ts();
		return $snapshot;
	}
}