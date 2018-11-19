<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Comments;

class Handler {

	/**
	 * @return EntryVO
	 */
	static public function getVo() {
		return new EntryVO();
	}
}