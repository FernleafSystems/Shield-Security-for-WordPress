<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;

/**
 * Class BaseIp
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops
 */
class BaseIp {

	/**
	 * @var string
	 */
	private $sIP;

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