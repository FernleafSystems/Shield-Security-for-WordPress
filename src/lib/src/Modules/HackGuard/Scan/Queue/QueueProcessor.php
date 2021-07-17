<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\ScanQueue;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities;

class QueueProcessor extends Utilities\BackgroundProcessing\BackgroundProcess {

	use Shield\Modules\ModConsumer;

	/**
	 * Get batch
	 *
	 * @return \stdClass Return the first batch from the queue
	 */
	protected function get_batch() {
		/** @var ScanQueue\Select $oSel */
		$oSel = $this->getDbHandler()->getQuerySelector();

		$oEntry = $oSel->filterByNotStarted()
					   ->filterByNotFinished()
					   ->setOrderBy( 'id', 'ASC', true )
					   ->first();

		$oBatch = new \stdClass();
		$oBatch->key = $oEntry->id;
		$oBatch->data = [ $oEntry ];
		return $oBatch;
	}

	/**
	 * Task
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param ScanQueue\EntryVO $entry Queue item to iterate over.
	 * @return mixed
	 */
	protected function task( $entry ) {
		$entry->started_at = Services::Request()->ts();
		/** @var ScanQueue\Update $updater */
		$updater = $this->getDbHandler()->getQueryUpdater();
		$updater->setStarted( $entry );

		try {
			( new ScanExecute() )
				->setMod( $this->getMod() )
				->execute( $entry );
		}
		catch ( \Exception $e ) {
//			error_log( $e->getMessage() );
		}

		$updater->setFinished( $entry );
		return $entry;
	}

	/**
	 * Complete.
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete() {
		parent::complete();

		( new CompleteQueue() )
			->setDbHandler( $this->getDbHandler() )
			->setMod( $this->getMod() )
			->complete();
	}

	/**
	 * Delete queue
	 *
	 * @param string $key Key.
	 * @return $this
	 */
	public function delete( $key ) {
		/** @var ScanQueue\Delete $oDel */
		$oDel = $this->getDbHandler()->getQueryDeleter();
		$oDel->deleteById( $key );
		return $this;
	}

	/**
	 * Is queue empty
	 *
	 * @return bool
	 */
	protected function is_queue_empty() {
		/** @var ScanQueue\Select $selector */
		$selector = $this->getDbHandler()->getQuerySelector();
		return $selector->filterByNotStarted()
						->filterByNotFinished()
						->count() === 0;
	}

	/**
	 * Save queue
	 *
	 * @return $this
	 */
	public function save() {

		if ( is_array( $this->data ) ) {
			/** @var ScanQueue\Insert $inserter */
			$inserter = $this->getDbHandler()->getQueryInserter();
			foreach ( $this->data as $nKey => $entry ) {
				/** @var ScanQueue\EntryVO $entry */
				if ( $entry instanceof ScanQueue\EntryVO ) {
					$inserter->insert( $entry );
				}
			}
		}

		$this->data = []; // critical to preventing duplicate entries
		return $this;
	}

	/**
	 * Update queue
	 *
	 * @param string              $key  Key.
	 * @param ScanQueue\EntryVO[] $data Data.
	 * @return $this
	 */
	public function update( $key, $data ) {
		/** @var ScanQueue\Update $updater */
		$updater = $this->getDbHandler()->getQueryUpdater();
		$entry = array_shift( $data );
		$updater->updateById( $entry->id, $entry->getRawData() );
		return $this;
	}

	public function handleExpiredItems() {
		$boundary = Services::Request()
							->carbon()
							->subSeconds( $this->getExpirationInterval() )->timestamp;
		$this->getDbHandler()->deleteRowsOlderThan( $boundary );
	}

	/**
	 * @return ScanQueue\Handler
	 */
	public function getDbHandler() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return $mod->getDbHandler_ScanQueue();
	}
}
