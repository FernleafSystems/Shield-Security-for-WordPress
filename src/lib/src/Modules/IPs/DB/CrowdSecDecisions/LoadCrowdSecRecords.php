<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\CrowdSecDecisions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\IpAddressConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class LoadCrowdSecRecords {

	use ModConsumer;
	use IpAddressConsumer;

	/**
	 * @throws \Exception
	 */
	public function loadRecord() :CrowdSecRecord {
		$records = $this->selectAll( 1 );
		if ( empty( $this->getIP() ) ) {
			throw new \Exception( 'Must supply IP!' );
		}
		if ( empty( $records ) ) {
			throw new \Exception( 'No record' );
		}
		return array_shift( $records );
	}

	/**
	 * @return CrowdSecRecord[]
	 */
	public function selectAll( int $limit = 0 ) :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$ip = $this->getIP();
		$raw = Services::WpDb()->selectCustom(
			sprintf( "SELECT ips.ip as ip, cs.*
						FROM `%s` as `cs`
						INNER JOIN `%s` as `ips`
							ON `ips`.id = `cs`.ip_ref 
							%s
						ORDER BY `cs`.`updated_at` DESC
						%s;",
				$mod->getDbH_CrowdSecDecisions()->getTableSchema()->table,
				$this->getCon()->getModule_Data()->getDbH_IPs()->getTableSchema()->table,
				empty( $ip ) ? '' : sprintf( "AND `ips`.`ip`=INET6_ATON('%s')", $ip ),
				$limit > 1 ? sprintf( 'LIMIT %s', $limit ) : ''
			)
		);

		return array_values( array_map(
			function ( $record ) {
				return ( new CrowdSecRecord() )->applyFromArray( $record );
			},
			is_array( $raw ) ? $raw : []
		) );
	}
}