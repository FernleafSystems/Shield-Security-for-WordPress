<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;

class WpCli extends Base\WpCli {

	/**
	 * @inheritDoc
	 */
	protected function getCmdHandlers() :array {
		return [
			new SecurityAdmin\WpCli\Pin(),
			new SecurityAdmin\WpCli\AdminAdd(),
			new SecurityAdmin\WpCli\AdminRemove()
		];
	}
}