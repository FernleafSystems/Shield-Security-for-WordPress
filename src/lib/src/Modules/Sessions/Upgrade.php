<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class Upgrade extends Base\Upgrade {

	/**
	 * Support full-length IPv6 addresses
	 */
	protected function upgrade_922() {
		/** @var \ICWP_WPSF_FeatureHandler_Sessions $mod */
		$mod = $this->getMod();
		$dbh = $mod->getDbHandler_Sessions();
		Services::WpDb()->doSql(
			sprintf( "ALTER TABLE `%s` MODIFY `%s` %s;",
				$dbh->getTable(), 'ip', $dbh->enumerateColumns()[ 'ip' ] )
		);
	}
}