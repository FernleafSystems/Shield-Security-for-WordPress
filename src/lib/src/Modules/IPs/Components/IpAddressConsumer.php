<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components;

use FernleafSystems\Wordpress\Plugin\Shield;

trait IpAddressConsumer {

	/**
	 * @var string
	 */
	private $ipAddress;

	/**
	 * @return string
	 */
	public function getIP() {
		return $this->ipAddress;
	}

	/**
	 * @param string $IP
	 * @return $this
	 */
	public function setIP( $IP ) {
		$this->ipAddress = $IP;
		return $this;
	}
}