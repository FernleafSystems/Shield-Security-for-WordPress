<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;

/**
 * Class DeleteIp
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops
 */
class DeleteIp extends BaseIp {

	use Shield\Databases\Base\HandlerConsumer;

	/**
	 * @var string
	 */
	private $sIP;

	/**
	 * @return bool
	 */
	public function fromBlacklist() {
		return $this->getDeleter()
					->filterByBlacklist()
					->query();
	}

	/**
	 * @return bool
	 */
	public function fromWhiteList() {
		return $this->getDeleter()
					->filterByWhitelist()
					->query();
	}

	/**
	 * @return IPs\Delete
	 */
	private function getDeleter() {
		/** @var IPs\Delete $oDel */
		$oDel = $this->getDbHandler()->getQueryDeleter();
		return $oDel->filterByIp( $this->getIP() )
					->setLimit( 1 );
	}

	/**
	 * @return string
	 */
	public function getIP() {
		return $this->sIP;
	}

	/**
	 * @param string $sIP
	 * @return $this
	 */
	public function setIP( $sIP ) {
		$this->sIP = $sIP;
		return $this;
	}
}