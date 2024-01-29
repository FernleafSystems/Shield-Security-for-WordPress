<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\Utility;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\{
	DB\Scans\Ops as ScansDB,
	ModConsumer
};
use FernleafSystems\Wordpress\Services\Services;

class Clean {

	use ExecOnce;
	use ModConsumer;

	protected function run() {
		$this->deleteScansThatNeverCompleted();
		$this->deleteEarlierScans();
	}

	private function deleteScansThatNeverCompleted() {
		/** @var ScansDB\Delete $deleter */
		$deleter = self::con()->db_con->dbhScans()->getQueryDeleter();
		$deleter->filterByNotFinished()
				->filterByCreatedAt( Services::Request()->carbon()->subDay()->timestamp, '<' )
				->query();
	}

	private function deleteEarlierScans() {
		$scanIDsToKeep = [];
		foreach ( $this->mod()->getScansCon()->getScanSlugs() as $scanSlug ) {
			/** @var ScansDB\Select $select */
			$select = self::con()->db_con->dbhScans()->getQuerySelector();
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
				self::con()->db_con->dbhScans()->getTableSchema()->table,
				\implode( ', ', $scanIDsToKeep ) ) );
		}
	}
}