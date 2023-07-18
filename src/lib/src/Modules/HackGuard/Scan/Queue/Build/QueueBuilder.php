<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Services\Utilities;

class QueueBuilder extends Utilities\BackgroundProcessing\BackgroundProcess {

	use HackGuard\ModConsumer;

	/**
	 * Get batch
	 *
	 * @return \stdClass Return the first batch from the queue
	 */
	protected function get_batch() {
		$scans = $this->opts()->getScansToBuild();
		$scan = \key( $scans );

		$batch = new \stdClass();
		$batch->key = $scan;
		$batch->data = [ $scan ];
		return $batch;
	}

	/**
	 * Dispatch
	 *
	 * @access public
	 * @return void
	 */
	public function dispatch() {
		// ensure any scheduled scans have been saved before remote post
		$this->save();
		// Perform remote post.
		parent::dispatch();
	}

	/**
	 * Task
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param string $slug .
	 * @return mixed
	 */
	protected function task( $slug ) {
		try {
			( new HackGuard\Scan\Queue\QueueInit() )->init( (string)$slug );
		}
		catch ( \Exception $e ) {
		}

		// deletes the scan from the to-be-built array
		return false;
	}

	/**
	 * Complete.
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete() {
		parent::complete();
		$this->mod()
			 ->getScanQueueController()
			 ->getQueueProcessor()
			 ->dispatch();
	}

	/**
	 * Delete queue
	 *
	 * @param string $scanSlug .
	 * @return $this
	 */
	public function delete( $scanSlug ) {
		$this->opts()->addRemoveScanToBuild( $scanSlug, false );
		$this->save();
		return $this;
	}

	/**
	 * Is queue empty
	 *
	 * @return bool
	 */
	protected function is_queue_empty() {
		return \count( $this->opts()->getScansToBuild() ) === 0;
	}

	/**
	 * Save queue
	 *
	 * @return $this
	 */
	public function save() {
		$this->mod()->saveModOptions();
		return $this;
	}

	/**
	 * Update queue
	 *
	 * @param string $key  Key.
	 * @param mixed  $data Data.
	 * @return $this
	 */
	public function update( $key, $data ) {
		return $this;
	}
}
