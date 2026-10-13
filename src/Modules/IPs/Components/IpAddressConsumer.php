<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components;

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
