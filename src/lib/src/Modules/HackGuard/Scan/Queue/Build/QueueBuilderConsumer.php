<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\Build;

/**
 * Trait QueueBuilderConsumer
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\Build
 */
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
	 * @param QueueBuilder $oQP
	 * @return $this
	 */
	public function setQueueBuilder( QueueBuilder $oQP ) {
		$this->oQueueBuilder = $oQP;
		return $this;
	}
}
