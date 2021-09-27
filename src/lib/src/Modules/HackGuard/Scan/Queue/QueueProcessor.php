<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\{
	ModCon,
	Scan\Init\QueueItems,
	Scan\Init\ScanQueueItemVO,
	Scan\Init\SetScanCompleted,
	Scan\Init\StoreResults};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ScanItems as ScanItemsDB;
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
		$batch = new \stdClass();

		try {
			$qItem = ( new QueueItems() )
				->setMod( $this->getMod() )
				->next();

			$batch->key = $qItem->qitem_id;
			$batch->data = [ $qItem ];
		}
		catch ( Shield\Modules\HackGuard\Scan\Exceptions\NoQueueItems $e ) {
			// This should never happen as "is_empty()" is called before
			error_log( $e->getMessage() );
		}

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
	 * @param ScanQueueItemVO $item Queue item to iterate over.
	 * @return mixed
	 */
	protected function task( $item ) {
		$this->getDbH_ScanItems()
			 ->getQueryUpdater()
			 ->updateById( $item->qitem_id, [
				 'started_at' => Services::Request()->ts()
			 ] );

		try {
			$results = ( new ScanExecute() )
				->setMod( $this->getMod() )
				->execute( $item );

			( new StoreResults() )
				->setMod( $this->getMod() )
				->store( $item, $results );

			$this->getDbH_ScanItems()
				 ->getQueryUpdater()
				 ->updateById( $item->qitem_id, [
					 'finished_at' => Services::Request()->ts()
				 ] );

			( new SetScanCompleted() )
				->setMod( $this->getMod() )
				->run();
		}
		catch ( \Exception $e ) {
			error_log( $e->getMessage() );
		}

		return $item;
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
		return !( new QueueItems() )
			->setMod( $this->getMod() )
			->hasNextItem();
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
		// Do nothing. Results are stored separately.
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
