<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\BotSignal;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\IpAddressConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class LoadBotSignalRecords {

	use PluginControllerConsumer;
	use IpAddressConsumer;

	/**
	 * @throws \Exception
	 */
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
				self::con()->db_con->bot_signals->getTable(),
				self::con()->db_con->ips->getTable(),
				$this->getIP()
			)
		);
		return \is_array( $raw ) ? $raw : [];
	}
}