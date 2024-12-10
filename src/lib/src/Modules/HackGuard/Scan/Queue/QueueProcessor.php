<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ScanItems\Ops as ScanItemsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Exceptions\NoQueueItems;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities;

class QueueProcessor extends Utilities\BackgroundProcessing\BackgroundProcess {

	use PluginControllerConsumer;

	protected function get_post_args() {
		$args = parent::get_post_args();

		/**
		 * Automatically add HTTP AUTH header if the current request has it.
		 */
		if ( Services::Request()->server[ 'HTTP_AUTHORIZATION' ] ?? false ) {
			if ( !\is_array( $args[ 'headers' ] ?? null ) ) {
				$args[ 'headers' ] = [];
			}
			$args[ 'headers' ][ 'Authorization' ] = Services::Request()->server[ 'HTTP_AUTHORIZATION' ];
		}

		return $args;
	}

	/**
	 * Get batch
	 *
	 * @return \stdClass Return the first batch from the queue
	 */
	protected function get_batch() {
		$batch = new \stdClass();

		try {
			$qItem = ( new QueueItems() )->next();

			$batch->key = $qItem->qitem_id;
			$batch->data = [ $qItem ];
		}
		catch ( NoQueueItems $e ) {
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
		( new ProcessQueueItem() )->run( $item );
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
		( new CompleteQueue() )->complete();
	}

	/**
	 * Delete queue
	 *
	 * @param string $key Key.
	 * @return $this
	 */
	public function delete( $key ) {
		self::con()
			->db_con
			->scan_items
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
		return !( new QueueItems() )->hasNextItem();
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
