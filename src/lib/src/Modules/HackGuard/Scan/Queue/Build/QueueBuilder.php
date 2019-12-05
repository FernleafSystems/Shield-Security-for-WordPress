<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Services\Utilities;

class QueueBuilder extends Utilities\BackgroundProcessing\BackgroundProcess {

	use HackGuard\Scan\Queue\QueueProcessorConsumer;
	use Shield\Modules\ModConsumer;

	/**
	 * Get batch
	 *
	 * @return \stdClass Return the first batch from the queue
	 */
	protected function get_batch() {
		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		$aScans = $oOpts->getScansToBuild();
		$sScan = key( $aScans );

		$oBatch = new \stdClass();
		$oBatch->key = $sScan;
		$oBatch->data = [ $sScan ];
		return $oBatch;
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
	 * @param string $sScanSlug .
	 * @return mixed
	 */
	protected function task( $sScanSlug ) {

		try {
			( new HackGuard\Scan\Queue\ScanInitiate() )
				->setMod( $this->getMod() )
				->setQueueProcessor( $this->getQueueProcessor() )
				->init( $sScanSlug );
		}
		catch ( \Exception $oE ) {
//			error_log( $oE->getMessage() );
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
		$this->getQueueProcessor()->dispatch();
	}

	/**
	 * Delete queue
	 *
	 * @param string $sScanSlug .
	 * @return $this
	 */
	public function delete( $sScanSlug ) {
		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		$oOpts->addRemoveScanToBuild( $sScanSlug, false );
		$this->save();
		return $this;
	}

	/**
	 * Is queue empty
	 *
	 * @return bool
	 */
	protected function is_queue_empty() {
		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		return count( $oOpts->getScansToBuild() ) === 0;
	}

	/**
	 * Save queue
	 *
	 * @return $this
	 */
	public function save() {
		$this->getMod()->saveModOptions();
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
