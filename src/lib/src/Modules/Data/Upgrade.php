<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class Upgrade extends Base\Upgrade {

	public function upgrade_1414() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$schema = $mod->getDbH_ReqLogs()->getTableSchema();
		$res = Services::WpDb()->doSql( sprintf( 'ALTER TABLE `%s` MODIFY COLUMN %s %s;',
			$schema->table,
			'path',
			$schema->enumerateColumns()[ 'path' ]
		) );
	}
}