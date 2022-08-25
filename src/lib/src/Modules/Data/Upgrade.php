<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data;

use FernleafSystems\Wordpress\Services\Services;

class Upgrade extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Upgrade {

	public function upgrade_1414() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$schema = $mod->getDbH_ReqLogs()->getTableSchema();
		Services::WpDb()->doSql( sprintf( 'ALTER TABLE `%s` MODIFY COLUMN %s %s;',
			$schema->table,
			'path',
			$schema->enumerateColumns()[ 'path' ]
		) );
	}
}