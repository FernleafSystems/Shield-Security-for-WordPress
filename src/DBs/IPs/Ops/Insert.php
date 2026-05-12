<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\IPs\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Common\IpAddressSql;
use FernleafSystems\Wordpress\Services\Services;

class Insert extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Insert {

	/**
	 * @param Record $record
	 */
	public function insert( $record ) :bool {
		return (bool)Services::WpDb()->doSql( sprintf(
			'INSERT IGNORE INTO `%s` (`%s`,`created_at`) VALUES (%s, %s)',
			$this->getDbH()->getTableSchema()->table,
			'ip',
			IpAddressSql::literalFromIp( $record->ip ),
			Services::Request()->ts()
		) );
	}
}
