<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

use FernleafSystems\Wordpress\Services\Services;

class Upgrade extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Upgrade {

	protected function upgrade_1828() {
		$schema = self::con()->db_con->dbhSnapshots()->getTableSchema();
		Services::WpDb()->doSql(
			sprintf( 'ALTER TABLE `%s` MODIFY `data` %s', $schema->table, $schema->enumerateColumns()[ 'data' ] )
		);
	}
}