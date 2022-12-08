<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Utility;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Record;
use FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Select;
use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Utilities;

abstract class QueueDbRecordsMigrator extends Utilities\BackgroundProcessing\BackgroundProcess {

	use ModConsumer;

	public const PAGE_SIZE = 100;

	/**
	 * Get batch
	 *
	 * @return \stdClass Return the first batch from the queue
	 */
	protected function get_batch() {
		$batch = new \stdClass();

		try {
			$batch->key = rand();
			$batch->data = $this->getNextItems();
		}
		catch ( \Exception $e ) {
			// This should never happen as "is_queue_empty()" is called before
			error_log( $e->getMessage() );
		}

		return $batch;
	}

	protected function getNextItems() :array {
		$result = $this->getDbSelector()
					   ->setLimit( static::PAGE_SIZE )
					   ->setPage( 1 )
					   ->queryWithResult();
		return is_array( $result ) ? $result : [];
	}

	/**
	 * @return Select|mixed
	 */
	abstract protected function getDbSelector();

	/**
	 * Task
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param Record $item Array of records to be processed.
	 * @return false
	 */
	protected function task( $item ) {
		try {
			$this->processRecord( $item );
		}
		catch ( \Exception $e ) {
			$this->cancel_process();
		}
		return false;
	}

	/**
	 * @param Record $record
	 * @return Record
	 * @throws \Exception
	 */
	abstract protected function processRecord( $record );

	/**
	 * Delete queue
	 *
	 * @param string $key Key.
	 * @return $this
	 */
	public function delete( $key ) {
		return $this;
	}

	/**
	 * Is queue empty
	 *
	 * @return bool
	 */
	protected function is_queue_empty() {
		return $this->getDbSelector()->count() === 0;
	}

	/**
	 * Save queue
	 *
	 * @return $this
	 */
	public function save() {
		return $this;
	}

	public function update( $key, $data ) {
		// Do nothing. Results are stored separately.
		return $this;
	}
}
