<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Handler extends Base\Handler {

	/**
	 * @return string
	 */
	protected function getNameSpace() {
		return __NAMESPACE__;
	}
}