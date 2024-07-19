<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Database;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops as ScansDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CleanScansDB {

	use PluginControllerConsumer;

	public function run() {
		$this->deleteScansThatNeverCompleted();
		$this->deleteEarlierScans();
	}

	private function deleteScansThatNeverCompleted() {
		/** @var ScansDB\Delete $deleter */
		$deleter = self::con()->db_con->scans->getQueryDeleter();
		$deleter->filterByNotFinished()
				->filterByCreatedAt( Services::Request()->carbon()->subDay()->timestamp, '<' )
				->query();
	}

	private function deleteEarlierScans() {
		$scanIDsToKeep = [];
		foreach ( self::con()->comps->scans->getScanSlugs() as $scanSlug ) {
			/** @var ScansDB\Select $select */
			$select = self::con()->db_con->scans->getQuerySelector();
			$scanRecords = $select->filterByFinished()
								  ->filterByScan( $scanSlug )
								  ->setOrderBy( 'finished_at' )
								  ->setLimit( 1 )
								  ->queryWithResult();
			if ( \is_array( $scanRecords ) && \count( $scanRecords ) === 1 ) {
				$scanIDsToKeep[] = $scanRecords[ 0 ]->id;
			}
		}

		if ( !empty( $scanIDsToKeep ) ) {
			Services::WpDb()->doSql( sprintf( 'DELETE FROM %s WHERE `id` NOT IN (%s) AND `finished_at`>0',
				self::con()->db_con->scans->getTable(),
				\implode( ', ', $scanIDsToKeep ) ) );
		}
	}
}