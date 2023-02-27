<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ScanItems\Ops as ScanItemsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Services\Utilities;

class QueueProcessor extends Utilities\BackgroundProcessing\BackgroundProcess {

	use Shield\Modules\ModConsumer;

	public function dispatch() {
		// Perform remote post.
		return parent::dispatch();
	}

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
	 * @param QueueItemVO $item Queue item to iterate over.
	 * @return QueueItemVO
	 */
	protected function task( $item ) {
		( new ProcessQueueItem() )
			->setMod( $this->getMod() )
			->run( $item );
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
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$mod->getDbH_ScanItems()
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
	 * @param string               $key  Key.
	 * @param ScanItemsDB\Record[] $data Data.
	 * @return $this
	 */
	public function update( $key, $data ) {
		// Do nothing. Results are stored separately.
		return $this;
	}

	public function handleExpiredItems() {
		( new CleanQueue() )->execute();
	}
}
