<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

class WpCli extends Base\WpCli {

	/**
	 * @inheritDoc
	 */
	protected function getCmdHandlers() {
		return [
			new AuditTrail\WpCli\Display()
		];
	}
}