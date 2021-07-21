<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class Upgrade extends Base\Upgrade {

	protected function upgrade_1021() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$schema = $mod->getDbHandler_FileLocker()->getTableSchema();
		Services::WpDb()->doSql(
			sprintf( "ALTER TABLE `%s` MODIFY `%s` %s;",
				$schema->table, 'content', $schema->enumerateColumns()[ 'content' ] )
		);
	}
}