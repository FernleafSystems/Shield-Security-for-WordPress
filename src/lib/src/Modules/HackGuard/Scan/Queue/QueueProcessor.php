<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\ScanQueue;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ScanItems as ScanItemsDB;
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
		/** @var ScanItemsDB\Ops\Select $select */
		$select = $this->getDbH_ScanItems()->getQuerySelector();

		/** @var ScanItemsDB\Ops\Record $record */
		$record = $select->filterByNotStarted()
						 ->filterByNotFinished()
						 ->setOrderBy( 'id', 'ASC', true )
						 ->first();

		$batch = new \stdClass();
		$batch->key = $record->id;
		$batch->data = [ $record ];
		return $batch;
	}

	/**
	 * Task
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param ScanItemsDB\Ops\Record $record Queue item to iterate over.
	 * @return mixed
	 */
	protected function task( $record ) {
		error_log( var_export( 'execute task', true ) );
		/** @var ScanItemsDB\Ops\Update $updater */
		$updater = $this->getDbH_ScanItems()->getQueryUpdater();
		$updater->setStarted( $record );
		$record->started_at = Services::Request()->ts();

		try {
			$results = ( new ScanExecute() )
				->setMod( $this->getMod() )
				->execute( $record );
			error_log( 'results: '.var_export( $results, true ) );
			// TODO store results
		}
		catch ( \Exception $e ) {
			error_log( $e->getMessage() );
		}

		$updater->setFinished( $record );
		return false; //deletes the record upon completion
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
		$this->getDbH_ScanItems()
			 ->getQueryDeleter()
			 ->deleteById( $key );
		return $this;
	}

	/**
	 * Is queue empty
	 *
	 * @return bool
	 */
	protected function is_queue_empty() {
		/** @var ScanItemsDB\Ops\Select $selector */
		$selector = $this->getDbH_ScanItems()->getQuerySelector();
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
		return $this;
	}

	/**
	 * Update queue
	 *
	 * @param string                   $key  Key.
	 * @param ScanItemsDB\Ops\Record[] $data Data.
	 * @return $this
	 */
	public function update( $key, $data ) {
		// Do nothing. We delete completed entries as we go.
		return $this;
	}

	public function handleExpiredItems() {
		$this->getDbH_ScanItems()->deleteRowsOlderThan(
			Services::Request()
					->carbon()
					->subSeconds( $this->getExpirationInterval() )->timestamp
		);
	}

	private function getDbH_ScanItems() :ScanItemsDB\Ops\Handler {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return $mod->getDbH_ScanItems();
	}
}
