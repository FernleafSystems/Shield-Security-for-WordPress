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
	 * @deprecated 11.2
	 */
	private $sIpAddress;

	/**
	 * @var string
	 */
	private $ipAddress;

	/**
	 * @return string
	 */
	public function getIP() {
		return $this->ipAddress ?? $this->sIpAddress;
	}

	/**
	 * @param string $IP
	 * @return $this
	 */
	public function setIP( $IP ) {
		$this->ipAddress = $IP;
		$this->sIpAddress = $IP;
		return $this;
	}
}