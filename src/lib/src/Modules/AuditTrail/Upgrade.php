<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

use FernleafSystems\Wordpress\Services\Services;

class Upgrade extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Upgrade {

	protected function upgrade_1826() {
		if ( get_user_count() > 10000 ) {
			( new Lib\Snapshots\Ops\Delete() )->delete( Auditors\Users::Slug() );
		}
	}

	protected function upgrade_1828() {
		$schema = self::con()->getModule_AuditTrail()->getDbH_Snapshots()->getTableSchema();
		Services::WpDb()->doSql(
			sprintf( 'ALTER TABLE `%s` MODIFY `data` %s', $schema->table, $schema->enumerateColumns()[ 'data' ] )
		);
	}
}