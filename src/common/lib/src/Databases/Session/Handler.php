<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Session;

class Handler {

	/**
	 * @return EntryVO
	 */
	static public function getVo() {
		return new EntryVO();
	}
}