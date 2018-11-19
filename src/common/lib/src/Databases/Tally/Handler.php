<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Tally;

class Handler {

	/**
	 * @return EntryVO
	 */
	static public function getVo() {
		return new EntryVO();
	}
}