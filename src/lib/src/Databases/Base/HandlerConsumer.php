<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

/**
 * Class HandlerConsumer
 * @package FernleafSystems\Wordpress\Plugin\Shield\Databases\Base
 */
trait HandlerConsumer {

	/**
	 * @var Handler
	 */
	private $oDbHandler;

	/**
	 * @return Handler|mixed
	 */
	public function getDbHandler() {
		return $this->oDbHandler;
	}

	/**
	 * @param Handler $oDbH
	 * @return $this
	 */
	public function setDbHandler( $oDbH ) {
		$this->oDbHandler = $oDbH;
		return $this;
	}
}