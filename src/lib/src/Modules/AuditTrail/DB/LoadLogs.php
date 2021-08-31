<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\IpAddressConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class LoadLogs {

	use ModConsumer;
	use IpAddressConsumer;

	/**
	 * @return LogRecord[]
	 */
	public function run() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$stdKeys = array_flip( array_merge(
			$mod->getDbH_Logs()
				->getTableSchema()
				->getColumnNames(),
			$this->getCon()
				 ->getModule_Plugin()
				 ->getDbH_IPs()
				 ->getTableSchema()
				 ->getColumnNames()
		) );

		$results = [];

		foreach ( $this->selectRaw() as $raw ) {
			if ( empty( $results[ $raw[ 'id' ] ] ) ) {
				$record = new LogRecord( array_intersect_key( $raw, $stdKeys ) );
				$results[ $raw[ 'id' ] ] = $record;
			}
			else {
				$record = $results[ $raw[ 'id' ] ];
			}

			$meta = $record->meta_data ?? [];
			$meta[ $raw[ 'meta_key' ] ] = $raw[ 'meta_value' ];
			$record->meta_data = $meta;
		}

		return $results;
	}

	/**
	 * @return array[]
	 */
	private function selectRaw() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$dbhIPs = $this->getCon()->getModule_Plugin()->getDbH_IPs();
		$ip = $this->getIP();
		return Services::WpDb()->selectCustom(
			sprintf( 'SELECT log.*, ips.ip as ip, meta.meta_key, meta.meta_value, meta.log_ref as id
						FROM `%s` as log
						%s
						INNER JOIN `%s` as ips
							ON log.ip_ref = ips.id 
						INNER JOIN `%s` as meta
							ON log.id = meta.log_ref 
						ORDER BY log.id DESC;',
				$mod->getDbH_Logs()->getTableSchema()->table,
				empty( $ip ) ? '' : sprintf( 'WHERE `ips.ip`="%s"', inet_pton( $ip ) ),
				$dbhIPs->getTableSchema()->table,
				$mod->getDbH_Meta()->getTableSchema()->table
			)
		);
	}
}