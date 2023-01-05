<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\{
	DB\Scans\Ops as ScansDB,
	ModCon,
	Options
};
use FernleafSystems\Wordpress\Services\Services;

class Clean extends ExecOnceModConsumer {

	protected function run() {
		$this->deleteScansThatNeverCompleted();
		$this->deleteEarlierScans();
	}

	private function deleteScansThatNeverCompleted() {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		/** @var ScansDB\Delete $deleter */
		$deleter = $mod->getDbH_Scans()->getQueryDeleter();
		$deleter->filterByNotFinished()
				->filterByCreatedAt( Services::Request()->carbon()->subDay()->timestamp, '<' )
				->query();
	}

	private function deleteEarlierScans() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();
		$dbhScan = $mod->getDbH_Scans();

		$scanIDsToKeep = [];
		foreach ( $opts->getScanSlugs() as $scanSlug ) {
			/** @var ScansDB\Select $select */
			$select = $dbhScan->getQuerySelector();
			$scanRecords = $select->filterByFinished()
								  ->filterByScan( $scanSlug )
								  ->setOrderBy( 'finished_at', 'DESC' )
								  ->setLimit( 1 )
								  ->queryWithResult();
			if ( is_array( $scanRecords ) && count( $scanRecords ) === 1 ) {
				$scanIDsToKeep[] = $scanRecords[ 0 ]->id;
			}
		}

		if ( !empty( $scanIDsToKeep ) ) {
			Services::WpDb()->doSql( sprintf( 'DELETE FROM %s WHERE `id` NOT IN (%s) AND `finished_at`>0',
				$mod->getDbH_Scans()->getTableSchema()->table,
				implode( ', ', $scanIDsToKeep ) ) );
		}
	}
}