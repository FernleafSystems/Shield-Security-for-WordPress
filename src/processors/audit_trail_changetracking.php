<?php

use FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\ChangeTracking;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Options;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_AuditTrail_ChangeTracking extends ICWP_WPSF_Processor_BaseDb {

	/**
	 */
	public function run() {
		if ( !$this->isReadyToRun() ) {
			return;
		}
	}

	public function runHourlyCron() {
		/** @var Options $oOpts */
		$oOpts = $this->getMod()->getOptions();
		if ( $oOpts->isCTSnapshotDue() && $this->isReadyToRun() ) {
			$oOpts->updateCTLastSnapshotAt();
			$this->runSnapshot();
		}
	}

	/**
	 * @return ChangeTracking\EntryVO
	 */
	private function buildSnapshot() {
		$oVo = new ChangeTracking\EntryVO();
		$oVo->data = ( new ChangeTrack\Snapshot\Collate() )->run();
		$oVo->meta = [
			'ts'      => Services::Request()->ts(),
			'version' => $this->getCon()->getVersion(),
		];
		return $oVo;
	}

	/**
	 * Should always use the appropriate VO to set the data and pass to insert()
	 * This ensures correct encoding of data for DB storage.
	 *
	 * Also, wherever this snapshot is run from, should update the "last snapshot at" as appropriate
	 * @return ChangeTracking\EntryVO
	 */
	private function runSnapshot() {
		$oVo = $this->buildSnapshot();
		/** @var ChangeTracking\Insert $oInsert */
		$oInsert = $this->getMod()
						->getDbHandler()
						->getQueryInserter();
		$oInsert->insert( $oVo );
		return $oVo;
	}
}