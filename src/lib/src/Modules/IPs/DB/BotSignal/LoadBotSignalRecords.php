<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\BotSignal;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\{
	Components\IpAddressConsumer,
	ModConsumer
};
use FernleafSystems\Wordpress\Services\Services;

/**
 * @deprecated 19.1
 */
class LoadBotSignalRecords {

	use ModConsumer;
	use IpAddressConsumer;

	public function loadRecord() :BotSignalRecord {
		$raw = $this->selectRaw();
		if ( empty( $raw ) ) {
			throw new \Exception( 'No record' );
		}
		return ( new BotSignalRecord() )->applyFromArray( $raw );
	}

	private function selectRaw() :array {
		$raw = Services::WpDb()->selectRow(
			sprintf( "SELECT `ips`.`ip`, `bs`.*
						FROM `%s` as `bs`
						INNER JOIN `%s` as `ips`
							ON `ips`.`id` = `bs`.`ip_ref` 
							AND `ips`.`ip`=INET6_ATON('%s')
						ORDER BY `bs`.`updated_at` DESC
						LIMIT 1;",
				self::con()->db_con->dbhBotSignal()->getTableSchema()->table,
				self::con()->db_con->dbhIPs()->getTableSchema()->table,
				$this->getIP()
			)
		);
		return \is_array( $raw ) ? $raw : [];
	}
}