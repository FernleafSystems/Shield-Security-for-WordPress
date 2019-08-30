<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ScanQueue;

/**
 * Trait QueueProcessorConsumer
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ScanQueue
 */
trait QueueProcessorConsumer {

	/**
	 * @var QueueProcessor
	 */
	private $oQueueProcessor;

	/**
	 * @return QueueProcessor
	 */
	public function getQueueProcessor() {
		return $this->oQueueProcessor;
	}

	/**
	 * @param QueueProcessor $oQP
	 * @return $this
	 */
	public function setQueueProcessor( QueueProcessor $oQP ) {
		$this->oQueueProcessor = $oQP;
		return $this;
	}
}
