<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

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