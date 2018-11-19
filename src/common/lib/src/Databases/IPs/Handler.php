<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;

class Handler {

	/**
	 * @return EntryVO
	 */
	static public function getVo() {
		return new EntryVO();
	}
}