<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class Upgrade extends Base\Upgrade {

	/**
	 * Support full-length IPv6 addresses
	 */
	protected function upgrade_922() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$schema = $mod->getDbHandler_Sessions()->getTableSchema();
		Services::WpDb()->doSql(
			sprintf( "ALTER TABLE `%s` MODIFY `%s` %s;",
				$schema->table, 'ip', $schema->enumerateColumns()[ 'ip' ] )
		);
	}
}