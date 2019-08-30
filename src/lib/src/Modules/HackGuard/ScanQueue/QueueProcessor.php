<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ScanQueue;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\ScanQueue;

class QueueProcessor extends \WP_Background_Process {

	use Shield\Modules\ModConsumer,
		Shield\Databases\Base\HandlerConsumer;

	/**
	 * @var string
	 */
	protected $action = 'shield_async_scans';

	/**
	 * Get batch
	 *
	 * @return \stdClass Return the first batch from the queue
	 */
	protected function get_batch() {
		/** @var ScanQueue\Handler $oDbh */
		$oDbh = $this->getDbHandler();
		/** @var ScanQueue\Select $oSel */
		$oSel = $oDbh->getQuerySelector();

		$oEntry = $oSel->filterByNotStarted()
					   ->filterByNotFinished()
					   ->setOrderBy( 'created_at', 'ASC' )
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
	 * @param ScanQueue\EntryVO $item Queue item to iterate over.
	 *
	 * @return mixed
	 */
	protected function task( $item ) {
		// run scan action from this spec

		// mark as finished to update the entry.
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
		// collate and publish scan results and clean all entries
	}

	/**
	 * Delete queue
	 *
	 * @param string $key Key.
	 * @return $this
	 */
	public function delete( $key ) {
		/** @var ScanQueue\Handler $oDbh */
		$oDbh = $this->getDbHandler();
		/** @var ScanQueue\Delete $oDel */
		$oDel = $oDbh->getQueryDeleter();
		$oDel->deleteById( $key );

		return $this;
	}

	/**
	 * Is queue empty
	 *
	 * @return bool
	 */
	protected function is_queue_empty() {
		/** @var ScanQueue\Handler $oDbh */
		$oDbh = $this->getDbHandler();
		/** @var ScanQueue\Select $oSel */
		$oSel = $oDbh->getQuerySelector();
		$nUnfinished = $oSel->filterByNotFinished()
							->count();
		return $nUnfinished > 0;
	}

	/**
	 * Save queue
	 *
	 * @return $this
	 */
	public function save() {
		if ( !empty( $this->data ) ) {
			/** @var ScanQueue\Handler $oDbh */
			$oDbh = $this->getDbHandler();
			/** @var ScanQueue\Insert $oInsert */
			$oInsert = $oDbh->getQueryInserter();
			foreach ( $this->data as $oEntry ) {
				if ( $oEntry instanceof ScanQueue\EntryVO ) {
					$oInsert->insert( $oEntry );
				}
			}
		}

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
		/** @var ScanQueue\Handler $oDbh */
		$oDbh = $this->getDbHandler();
		/** @var ScanQueue\Update $oUpd */
		$oUpd = $oDbh->getQueryUpdater();
		$oUpd->updateEntry( array_shift( $data ) );

		return $this;
	}
}
