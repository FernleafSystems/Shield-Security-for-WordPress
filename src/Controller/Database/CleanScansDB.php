<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Database;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops as ScansDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CleanScansDB {

	use PluginControllerConsumer;

	public function run() {
		$this->deleteOldUnfinishedScans();
		$this->deleteOldScopedAfsRuns();
		$this->deleteEarlierCompletedFullScans();
	}

	private function deleteOldUnfinishedScans() {
		/** @var ScansDB\Delete $deleter */
		$deleter = self::con()->db_con->scans->getQueryDeleter();
		$deleter->filterByNotFinished()
				->filterByCreatedAt( Services::Request()->carbon()->subDay()->timestamp, '<' )
				->query();

		Services::WpDb()->doSql( sprintf(
			"DELETE FROM `%s`
			 WHERE `finished_at`>0
			   AND `status`='failed'
			   AND `finished_at`<%d;",
			self::con()->db_con->scans->getTable(),
			Services::Request()->carbon()->subDay()->timestamp
		) );
	}

	private function deleteOldScopedAfsRuns() :void {
		Services::WpDb()->doSql( sprintf(
			"DELETE FROM `%s`
			 WHERE `finished_at`>0
			   AND `scan`='afs'
			   AND `scope_type`!='full'
			   AND `finished_at`<%d;",
			self::con()->db_con->scans->getTable(),
			Services::Request()->carbon()->subDay()->timestamp
		) );
	}

	private function deleteEarlierCompletedFullScans() {
		$scanIDsToKeep = [];
		foreach ( self::con()->comps->scans->getScanSlugs() as $scanSlug ) {
			/** @var ScansDB\Select $select */
			$select = self::con()->db_con->scans->getQuerySelector();
			$scanRecords = $select->filterByFinished()
								  ->filterByStatus( 'completed' )
								  ->filterByScan( $scanSlug )
								  ->addWhereEquals( 'scope_type', 'full' )
								  ->setOrderBy( 'finished_at' )
								  ->setLimit( 1 )
								  ->queryWithResult();
			if ( \is_array( $scanRecords ) && \count( $scanRecords ) === 1 ) {
				$scanIDsToKeep[] = $scanRecords[ 0 ]->id;
			}
		}

		if ( !empty( $scanIDsToKeep ) ) {
			Services::WpDb()->doSql( sprintf( "DELETE FROM `%s` WHERE `id` NOT IN (%s) AND `finished_at`>0 AND `status`='completed' AND `scope_type`='full'",
				self::con()->db_con->scans->getTable(),
				\implode( ', ', $scanIDsToKeep ) ) );
		}
	}
}
