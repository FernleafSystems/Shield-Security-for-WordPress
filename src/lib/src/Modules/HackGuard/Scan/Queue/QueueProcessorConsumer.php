<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

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
