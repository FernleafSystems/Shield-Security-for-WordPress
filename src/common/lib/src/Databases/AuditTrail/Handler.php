<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail;

class Handler {

	/**
	 * @return EntryVO
	 */
	static public function getVo() {
		return new EntryVO();
	}
}