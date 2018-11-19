<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\AdminNotes;

class Handler {

	/**
	 * @return EntryVO
	 */
	static public function getVo() {
		return new EntryVO();
	}
}