<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\BotSignal;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\IpAddressConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Services\Services;

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
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$raw = Services::WpDb()->selectRow(
			sprintf( "SELECT ips.ip, bs.*
						FROM `%s` as bs
						INNER JOIN `%s` as ips
							ON `ips`.id = `bs`.ip_ref 
							AND `ips`.`ip`=INET6_ATON('%s')
						ORDER BY `bs`.updated_at DESC
						LIMIT 1;",
				$mod->getDbH_BotSignal()->getTableSchema()->table,
				$this->getCon()->getModule_Data()->getDbH_IPs()->getTableSchema()->table,
				$this->getIP()
			)
		);
		return is_array( $raw ) ? $raw : [];
	}
}