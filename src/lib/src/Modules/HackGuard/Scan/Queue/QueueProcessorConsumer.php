<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

/**
 * @deprecated 12.1
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
	 * @param QueueProcessor $QP
	 * @return $this
	 */
	public function setQueueProcessor( QueueProcessor $QP ) {
		$this->oQueueProcessor = $QP;
		return $this;
	}
}
