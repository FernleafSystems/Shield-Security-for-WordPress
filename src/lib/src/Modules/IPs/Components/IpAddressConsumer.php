<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components;

use FernleafSystems\Wordpress\Plugin\Shield;

/**
 * Trait BaseIp
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components
 */
trait IpAddressConsumer {

	/**
	 * @var string
	 */
	private $sIpAddress;

	/**
	 * @return string
	 */
	public function getIP() {
		return $this->sIpAddress;
	}

	/**
	 * @param string $sIP
	 * @return $this
	 */
	public function setIP( $sIP ) {
		$this->sIpAddress = $sIP;
		return $this;
	}
}