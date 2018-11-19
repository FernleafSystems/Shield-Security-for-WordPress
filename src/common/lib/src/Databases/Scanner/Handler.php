<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;

class Handler {

	/**
	 * @return EntryVO
	 */
	static public function getVo() {
		return new EntryVO();
	}
}