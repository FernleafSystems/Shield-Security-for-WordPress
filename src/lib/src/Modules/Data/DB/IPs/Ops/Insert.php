<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IPs\Ops;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Base;
use FernleafSystems\Wordpress\Services\Services;

class Insert extends Base\Insert {

	/**
	 * @param Record $record
	 */
	public function insert( $record ) :bool {
		return (bool)Services::WpDb()->doSql( sprintf(
			"INSERT IGNORE INTO `%s` (`%s`,`created_at`) VALUES (INET6_ATON('%s'), %s)",
			$this->getDbH()->getTableSchema()->table,
			'ip',
			$record->ip,
			Services::Request()->ts()
		) );
	}
}