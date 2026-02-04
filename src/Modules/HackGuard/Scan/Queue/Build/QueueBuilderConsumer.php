<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\Build;

trait QueueBuilderConsumer {

	/**
	 * @var QueueBuilder
	 */
	private $oQueueBuilder;

	/**
	 * @return QueueBuilder
	 */
	public function getQueueBuilder() {
		return $this->oQueueBuilder;
	}

	/**
	 * @param QueueBuilder $QB
	 * @return $this
	 */
	public function setQueueBuilder( QueueBuilder $QB ) {
		$this->oQueueBuilder = $QB;
		return $this;
	}
}
