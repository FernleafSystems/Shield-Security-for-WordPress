<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Traffic;

class Handler {

	/**
	 * @return EntryVO
	 */
	static public function getVo() {
		return new EntryVO();
	}
}