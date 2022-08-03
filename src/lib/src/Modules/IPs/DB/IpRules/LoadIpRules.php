<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\IpAddressConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class LoadIpRules {

	use ModConsumer;

	public function loadRecord( string $ip, ) :IpRuleRecord {
		$raw = $this->selectRaw();
		if ( empty( $raw ) ) {
			throw new \Exception( 'No record' );
		}
		return ( new IpRuleRecord() )->applyFromArray( $raw );
	}

	private function selectRaw() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$raw = Services::WpDb()->selectRow(
			sprintf( "SELECT `ips`.`ip` as `ip`, `ir`.*
						FROM `%s` as `ir`
						INNER JOIN `%s` as `ips`
							ON `ips`.id = `ir`.ip_ref 
							AND `ips`.`ip`=INET6_ATON('%s')
						ORDER BY `ir`.updated_at DESC
						LIMIT 1;",
				$mod->getDbH_IPRules()->getTableSchema()->table,
				$this->getCon()->getModule_Data()->getDbH_IPs()->getTableSchema()->table,
				$this->getIP()
			)
		);
		return is_array( $raw ) ? $raw : [];
	}
}